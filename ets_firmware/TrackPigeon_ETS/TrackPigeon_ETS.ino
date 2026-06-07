/*
  TrackPigeon ETS — Single RDM6300 ULTRA FAST
  Optimasi: 100ms debounce, GPS fix, FIFO queue cepat
  Wiring sama dengan V3.0
*/

#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <RTClib.h>
#include <WebServer.h>
#include <TinyGPS++.h>
#include <ctype.h>
#include <esp_system.h>
#include "mbedtls/md.h"

// ===== KONFIGURASI WIFI & SERVER =====
const char* WIFI_SSID = "";
const char* WIFI_PASSWORD = "";
const char* SERVER_HOST = "trackpigeon.hnzproject.net";
const uint16_t SERVER_PORT = 443;
const bool USE_HTTPS = true;
const char* API_PATH = "/api/v1/ets/checkin.php";

// ===== DEVICE CREDENTIALS =====
const char* DEVICE_SERIAL = "";
const char* DEVICE_TOKEN = "";
const char* DEVICE_SECRET = "";
const char* FIRMWARE_VERSION = "trackpigeon-ets-s2-v1.1.0-fast";

const bool SCAN_ONLY_MODE = false;
const bool RFID_SEND_LAST_8 = false;

// ===== PIN MAP =====
#define RFID1_RX 16
#define RFID1_TX 17
#define BUZZER_PIN 21
#define RTC_SDA 33
#define RTC_SCL 34
#define LED_GREEN_PIN 5
#define LED_RED_PIN 4
#define SWITCH_PIN 15

// GPS pin — opsional, boleh tidak dipasang
#define GPS_RX 12
#define GPS_TX 13

const bool LED_ACTIVE_HIGH = true;
const bool BUZZER_ACTIVE_HIGH = true;

// ===== TIMING (ULTRA FAST) =====
const uint32_t WIFI_CONNECT_TIMEOUT_MS = 15000;
const uint32_t WIFI_RETRY_INTERVAL_MS = 5000;
const uint32_t HEARTBEAT_INTERVAL_MS = 300000;
const uint32_t QUEUE_RETRY_INTERVAL_MS = 800;   // Lebih cepat: 800ms
const uint32_t HTTP_TIMEOUT_MS = 3000;
const uint32_t TAG_DUPLICATE_WINDOW_MS = 100;    // SUPER CEPAT: 100ms!
const uint8_t QUEUE_SIZE = 50;

struct ScanItem {
  char rfid[16];
  char timestamp[20];
  bool gpsFix;
  double lat;
  double lng;
  uint32_t queuedAt;
  uint8_t attempts;
};

enum SendResult : uint8_t { SEND_OK, SEND_RETRY, SEND_REJECTED };

HardwareSerial RFIDSerial(1);
HardwareSerial GPSSerial(0);
RTC_DS3231 rtc;
TinyGPSPlus gps;
WebServer web(80);

ScanItem scanQueue[QUEUE_SIZE];
uint8_t queueHead = 0, queueTail = 0, queueCount = 0;

char rfidFrame[14];
uint8_t rfidFrameIndex = 0;
char lastTag[16] = "";
uint32_t lastTagMillis = 0;

bool rtcReady = false, clockReady = false, softClockReady = false;
DateTime softClockBase = DateTime(2000, 1, 1, 0, 0, 0);
uint32_t softClockMillis = 0;

bool gpsReady = false, gpsConfigured = false;
double gpsLat = 0.0, gpsLng = 0.0;

uint32_t lastWifiRetry = 0, lastHeartbeat = 0, lastQueueAttempt = 0, lastLedTick = 0;
bool ledPulseState = false;

String wifiStatus = "offline", wifiIp = "-";
String rtcStatus = "not ready", gpsStatus = "no fix";
String serverStatus = "not synced";
String lastRFID = "-", lastTime = "-", lastStatus = "booting", lastServerMessage = "-";
uint32_t successCount = 0, rejectedCount = 0, retryCount = 0;

// ===== FUNCTION DECLARATIONS =====
void setupWiFi();
bool syncWithServer();
void pollRFID();
void pollGPS();
void processTag(const char* tag);
bool pushScan(const char* rfid, const char* timestamp);
void processQueue();
SendResult sendScan(const ScanItem& item, String& message);
bool postJson(const String& payload, String& response, int& httpCode);
String buildApiUrl();
String hmacSha256(const String& message, const char* key);
String makeNonce();
bool parseRdm6300Frame(const char frame[14], char* outTag, size_t outSize);
int hexNibble(char c);
bool hexPairToByte(char high, char low, uint8_t& out);
bool getTimestamp(char* out, size_t outSize);
bool currentDateTime(DateTime& out);
bool parseServerTime(const char* text, DateTime& out);
void syncClock(const char* serverTimeText);
void copyText(char* target, size_t targetSize, const char* source);
bool tokenConfigured();
bool secretConfigured();
void manageWiFi();
void updateIdleLeds();
void writeLed(int pin, bool on);
void writeBuzzer(bool on);
void beep(uint8_t count, uint16_t onMs = 45, uint16_t offMs = 35);
void indicateSuccess();
void indicateRejected();
void indicateQueued();
void handleRoot();
void handleStatus();

// ===== SETUP =====
void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(LED_GREEN_PIN, OUTPUT);
  pinMode(LED_RED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(SWITCH_PIN, INPUT_PULLUP);
  writeLed(LED_GREEN_PIN, false);
  writeLed(LED_RED_PIN, false);
  writeBuzzer(false);

  Serial.println();
  Serial.println("╔══════════════════════════════════════╗");
  Serial.println("║  TrackPigeon ETS — SINGLE RDM6300   ║");
  Serial.println("║  ULTRA FAST 100ms | GPS Fix        ║");
  Serial.println("╚══════════════════════════════════════╝");
  Serial.println("Endpoint: " + buildApiUrl());

  // RTC
  Wire.begin(RTC_SDA, RTC_SCL);
  if (rtc.begin()) {
    rtcReady = true;
    if (rtc.lostPower()) {
      rtcStatus = "needs server sync";
      clockReady = false;
    } else {
      rtcStatus = "ok";
      clockReady = true;
    }
    Serial.println("[RTC] ✅ Ready");
  } else {
    rtcStatus = "not detected";
    Serial.println("[RTC] ❌ Not detected");
  }

  // RFID Single Reader
  RFIDSerial.begin(9600, SERIAL_8N1, RFID1_RX, RFID1_TX);
  Serial.println("[RFID] ✅ Ready on Pin " + String(RFID1_RX));

  // GPS — coba init, tapi jangan blocking
  GPSSerial.begin(9600, SERIAL_8N1, GPS_RX, GPS_TX);
  gpsConfigured = true;
  Serial.println("[GPS] Initializing... (non-blocking)");

  // WiFi
  setupWiFi();
  if (WiFi.status() == WL_CONNECTED) {
    syncWithServer();
  }

  // Web Server
  web.on("/", handleRoot);
  web.on("/status", handleStatus);
  web.begin();

  beep(2);
  lastStatus = SCAN_ONLY_MODE ? "scan only" : "ready";
  Serial.println("[READY] ⚡ 100ms debounce — Tempelkan tag!");
  Serial.println();
}

// ===== LOOP =====
void loop() {
  web.handleClient();
  pollGPS();    // Non-blocking GPS
  pollRFID();   // Ultra-fast RFID
  manageWiFi();

  const uint32_t now = millis();

  // Proses queue lebih cepat
  if (!SCAN_ONLY_MODE && queueCount > 0 && now - lastQueueAttempt >= QUEUE_RETRY_INTERVAL_MS) {
    lastQueueAttempt = now;
    processQueue();
  }

  // Heartbeat berkala
  if (!SCAN_ONLY_MODE && WiFi.status() == WL_CONNECTED && now - lastHeartbeat >= HEARTBEAT_INTERVAL_MS) {
    lastHeartbeat = now;
    syncWithServer();
  }

  updateIdleLeds();
  delay(1);
}

// ===== WIFI =====
void setupWiFi() {
  wifiStatus = "connecting";
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  const uint32_t start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT_MS) {
    delay(250);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    wifiStatus = "connected";
    wifiIp = WiFi.localIP().toString();
    Serial.println("[WIFI] ✅ " + wifiIp);
  } else {
    wifiStatus = "offline";
    wifiIp = "-";
    Serial.println("[WIFI] ❌ Offline");
  }
}

void manageWiFi() {
  const uint32_t now = millis();
  if (WiFi.status() == WL_CONNECTED) {
    wifiStatus = "connected";
    wifiIp = WiFi.localIP().toString();
    return;
  }
  wifiStatus = "offline";
  wifiIp = "-";
  if (now - lastWifiRetry >= WIFI_RETRY_INTERVAL_MS) {
    lastWifiRetry = now;
    WiFi.disconnect(false);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }
}

// ===== SERVER SYNC =====
bool syncWithServer() {
  if (WiFi.status() != WL_CONNECTED || !tokenConfigured()) return false;

  StaticJsonDocument<512> doc;
  doc["serial"] = DEVICE_SERIAL;
  doc["device_id"] = DEVICE_SERIAL;
  doc["firmware_version"] = FIRMWARE_VERSION;
  doc["rtc_status"] = rtcStatus;
  doc["gps_status"] = gpsStatus;
  doc["queue_count"] = queueCount;
  if (gpsReady) { doc["lat"] = gpsLat; doc["lng"] = gpsLng; doc["lon"] = gpsLng; }

  String payload;
  serializeJson(doc, payload);

  String response;
  int httpCode = -1;
  if (!postJson(payload, response, httpCode)) { serverStatus = "http error"; return false; }

  StaticJsonDocument<512> res;
  if (deserializeJson(res, response) || !res["ok"].as<bool>()) { serverStatus = "rejected"; return false; }

  const char* serverTimeText = res["server_time"] | "";
  if (strlen(serverTimeText) >= 19) syncClock(serverTimeText);

  serverStatus = "online";
  indicateSuccess();
  return true;
}

// ===== RFID POLLING (ULTRA FAST SINGLE READER) =====
void pollRFID() {
  while (RFIDSerial.available() > 0) {
    const char b = (char)RFIDSerial.read();
    
    // Deteksi start frame
    if (b == 0x02) {
      rfidFrameIndex = 0;
      rfidFrame[rfidFrameIndex++] = b;
      continue;
    }

    if (rfidFrameIndex == 0) continue;

    rfidFrame[rfidFrameIndex++] = b;
    
    // Frame lengkap (14 byte)
    if (rfidFrameIndex >= sizeof(rfidFrame)) {
      char tag[16];
      if (parseRdm6300Frame(rfidFrame, tag, sizeof(tag))) {
        processTag(tag);
      } else {
        rejectedCount++;
        indicateRejected();
      }
      rfidFrameIndex = 0;
    }
  }
}

// ===== GPS (NON-BLOCKING) =====
void pollGPS() {
  if (!gpsConfigured) return;

  bool newData = false;
  while (GPSSerial.available() > 0) {
    if (gps.encode((char)GPSSerial.read())) {
      newData = true;
    }
  }

  if (newData && gps.location.isValid() && gps.location.age() < 5000) {
    gpsLat = gps.location.lat();
    gpsLng = gps.location.lng();
    gpsReady = true;
    gpsStatus = "✅ Fix";
    Serial.println("[GPS] ✅ Fix: " + String(gpsLat, 6) + ", " + String(gpsLng, 6));
  } else if (gps.charsProcessed() > 0 && !gpsReady) {
    gpsStatus = "🔍 Searching...";
  }
}

// ===== PROCESS TAG (100ms DEBOUNCE) =====
void processTag(const char* tag) {
  const uint32_t now = millis();
  
  // Cek duplikat dalam 100ms
  if (strcmp(lastTag, tag) == 0 && now - lastTagMillis < TAG_DUPLICATE_WINDOW_MS) {
    return;
  }

  copyText(lastTag, sizeof(lastTag), tag);
  lastTagMillis = now;
  lastRFID = String(tag);

  char timestamp[20];
  const bool hasTime = getTimestamp(timestamp, sizeof(timestamp));
  lastTime = hasTime ? String(timestamp) : "-";

  Serial.print("[SCAN] UID: ");
  Serial.print(tag);
  Serial.print(" | Time: ");
  Serial.println(lastTime);

  if (SCAN_ONLY_MODE) {
    lastStatus = "scan only";
    indicateQueued();
    return;
  }

  if (!hasTime) {
    rejectedCount++;
    lastStatus = "clock not synced";
    indicateRejected();
    return;
  }

  if (pushScan(tag, timestamp)) {
    lastStatus = "queued";
    indicateQueued();
    Serial.print("[QUEUE] "); Serial.print(queueCount); Serial.println("/" + String(QUEUE_SIZE));
  } else {
    rejectedCount++;
    lastStatus = "queue full";
    indicateRejected();
  }
}

// ===== QUEUE =====
bool pushScan(const char* rfid, const char* timestamp) {
  if (queueCount >= QUEUE_SIZE) return false;

  ScanItem& item = scanQueue[queueTail];
  copyText(item.rfid, sizeof(item.rfid), rfid);
  copyText(item.timestamp, sizeof(item.timestamp), timestamp);
  item.gpsFix = gpsReady;
  item.lat = gpsLat;
  item.lng = gpsLng;
  item.queuedAt = millis();
  item.attempts = 0;

  queueTail = (queueTail + 1) % QUEUE_SIZE;
  queueCount++;
  return true;
}

void processQueue() {
  if (queueCount == 0 || WiFi.status() != WL_CONNECTED) return;

  ScanItem& item = scanQueue[queueHead];
  String message;
  SendResult result = sendScan(item, message);

  if (result == SEND_OK) {
    successCount++;
    lastStatus = "accepted";
    queueHead = (queueHead + 1) % QUEUE_SIZE;
    queueCount--;
    indicateSuccess();
    Serial.print("[OK] "); Serial.print(item.rfid); Serial.print(" | Queue: "); Serial.println(queueCount);
    return;
  }

  if (result == SEND_REJECTED) {
    rejectedCount++;
    lastStatus = "rejected";
    queueHead = (queueHead + 1) % QUEUE_SIZE;
    queueCount--;
    indicateRejected();
    Serial.print("[REJECTED] "); Serial.println(item.rfid);
    return;
  }

  item.attempts++;
  retryCount++;
}

// ===== SEND TO SERVER =====
SendResult sendScan(const ScanItem& item, String& message) {
  const String timestamp = String(item.timestamp);
  const String nonce = makeNonce();
  const String signedPayload = String(DEVICE_SERIAL) + "|" + String(item.rfid) + "|" + timestamp + "|" + nonce;
  const String signature = hmacSha256(signedPayload, DEVICE_SECRET);

  StaticJsonDocument<768> doc;
  doc["serial"] = DEVICE_SERIAL;
  doc["device_id"] = DEVICE_SERIAL;
  doc["device_token"] = DEVICE_TOKEN;
  doc["rfid_tag"] = item.rfid;
  doc["timestamp"] = timestamp;
  doc["nonce"] = nonce;
  doc["hmac"] = signature;
  doc["gps_fix"] = item.gpsFix;
  doc["queued_ms"] = (uint32_t)(millis() - item.queuedAt);
  if (item.gpsFix) { doc["lat"] = item.lat; doc["lng"] = item.lng; doc["lon"] = item.lng; }

  String payload;
  serializeJson(doc, payload);

  String response;
  int httpCode = -1;
  if (!postJson(payload, response, httpCode)) {
    message = "HTTP " + String(httpCode);
    return SEND_RETRY;
  }

  StaticJsonDocument<768> res;
  if (!deserializeJson(res, response)) {
    message = String((const char*)(res["message"] | ""));
    if (res["server_time"]) syncClock(res["server_time"]);
    if (res["ok"].as<bool>()) return SEND_OK;
  }

  if (httpCode >= 400 && httpCode <= 422) return SEND_REJECTED;
  return SEND_RETRY;
}

// ===== HTTP POST =====
bool postJson(const String& payload, String& response, int& httpCode) {
  httpCode = -1;
  response = "";
  HTTPClient http;
  WiFiClient plainClient;
  WiFiClientSecure secureClient;
  const String url = buildApiUrl();

  bool started = false;
  if (USE_HTTPS) { secureClient.setInsecure(); started = http.begin(secureClient, url); }
  else { started = http.begin(plainClient, url); }

  if (!started) return false;

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-ETS-Token", DEVICE_TOKEN);

  httpCode = http.POST(payload);
  if (httpCode > 0) response = http.getString();
  http.end();
  return httpCode > 0;
}

String buildApiUrl() {
  String url = USE_HTTPS ? "https://" : "http://";
  url += SERVER_HOST;
  if ((USE_HTTPS && SERVER_PORT != 443) || (!USE_HTTPS && SERVER_PORT != 80)) {
    url += ":"; url += String(SERVER_PORT);
  }
  url += API_PATH;
  return url;
}

// ===== HMAC SHA256 =====
String hmacSha256(const String& message, const char* key) {
  byte result[32];
  mbedtls_md_context_t ctx;
  const mbedtls_md_info_t* info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
  mbedtls_md_init(&ctx);
  mbedtls_md_setup(&ctx, info, 1);
  mbedtls_md_hmac_starts(&ctx, (const unsigned char*)key, strlen(key));
  mbedtls_md_hmac_update(&ctx, (const unsigned char*)message.c_str(), message.length());
  mbedtls_md_hmac_finish(&ctx, result);
  mbedtls_md_free(&ctx);
  char hex[65];
  for (uint8_t i = 0; i < 32; i++) sprintf(hex + (i * 2), "%02x", result[i]);
  hex[64] = '\0';
  return String(hex);
}

String makeNonce() {
  char buffer[24];
  snprintf(buffer, sizeof(buffer), "%08lx%08lx", (unsigned long)esp_random(), (unsigned long)millis());
  return String(buffer);
}

// ===== RDM6300 PARSER =====
bool parseRdm6300Frame(const char frame[14], char* outTag, size_t outSize) {
  if (frame[0] != 0x02 || frame[13] != 0x03) return false;
  for (uint8_t i = 1; i <= 12; i++) if (!isxdigit((unsigned char)frame[i])) return false;
  uint8_t checksum = 0;
  if (!hexPairToByte(frame[11], frame[12], checksum)) return false;
  uint8_t calculated = 0;
  for (uint8_t i = 1; i <= 9; i += 2) {
    uint8_t value = 0;
    if (!hexPairToByte(frame[i], frame[i + 1], value)) return false;
    calculated ^= value;
  }
  if (calculated != checksum) return false;
  char fullTag[11];
  for (uint8_t i = 0; i < 10; i++) fullTag[i] = (char)toupper((unsigned char)frame[i + 1]);
  fullTag[10] = '\0';
  copyText(outTag, outSize, RFID_SEND_LAST_8 ? fullTag + 2 : fullTag);
  return true;
}

int hexNibble(char c) {
  if (c >= '0' && c <= '9') return c - '0';
  c = (char)toupper((unsigned char)c);
  if (c >= 'A' && c <= 'F') return c - 'A' + 10;
  return -1;
}

bool hexPairToByte(char high, char low, uint8_t& out) {
  const int hi = hexNibble(high), lo = hexNibble(low);
  if (hi < 0 || lo < 0) return false;
  out = (uint8_t)((hi << 4) | lo);
  return true;
}

// ===== TIMESTAMP =====
bool getTimestamp(char* out, size_t outSize) {
  DateTime now;
  if (!currentDateTime(now)) { copyText(out, outSize, ""); return false; }
  snprintf(out, outSize, "%04u-%02u-%02u %02u:%02u:%02u", now.year(), now.month(), now.day(), now.hour(), now.minute(), now.second());
  return true;
}

bool currentDateTime(DateTime& out) {
  if (rtcReady && clockReady) { out = rtc.now(); return true; }
  if (softClockReady) {
    out = softClockBase + TimeSpan((millis() - softClockMillis) / 1000);
    return true;
  }
  return false;
}

bool parseServerTime(const char* text, DateTime& out) {
  if (text == nullptr || strlen(text) < 19) return false;
  const int y = atoi(text), m = atoi(text + 5), d = atoi(text + 8);
  const int h = atoi(text + 11), min = atoi(text + 14), s = atoi(text + 17);
  if (y < 2024 || m < 1 || m > 12 || d < 1 || d > 31 || h < 0 || h > 23 || min < 0 || min > 59 || s < 0 || s > 59) return false;
  out = DateTime(y, m, d, h, min, s);
  return true;
}

void syncClock(const char* serverTimeText) {
  DateTime serverTime;
  if (!parseServerTime(serverTimeText, serverTime)) return;
  softClockBase = serverTime;
  softClockMillis = millis();
  softClockReady = true;
  clockReady = true;
  if (rtcReady) { rtc.adjust(serverTime); rtcStatus = "synced"; }
  serverStatus = "synced";
}

void copyText(char* target, size_t targetSize, const char* source) {
  if (targetSize == 0) return;
  if (source == nullptr) { target[0] = '\0'; return; }
  strncpy(target, source, targetSize - 1);
  target[targetSize - 1] = '\0';
}

bool tokenConfigured() { return String(DEVICE_TOKEN).length() > 20 && !String(DEVICE_TOKEN).startsWith("PASTE_"); }
bool secretConfigured() { return String(DEVICE_SECRET).length() > 20 && !String(DEVICE_SECRET).startsWith("PASTE_"); }

// ===== LED & BUZZER =====
void updateIdleLeds() {
  const uint32_t now = millis();
  if (now - lastLedTick < 500) return;
  lastLedTick = now;
  ledPulseState = !ledPulseState;

  if (WiFi.status() == WL_CONNECTED && (serverStatus == "online" || serverStatus == "synced")) {
    writeLed(LED_GREEN_PIN, true);
  } else {
    writeLed(LED_GREEN_PIN, ledPulseState);
  }
  writeLed(LED_RED_PIN, false);
}

void writeLed(int pin, bool on) { digitalWrite(pin, LED_ACTIVE_HIGH ? (on ? HIGH : LOW) : (on ? LOW : HIGH)); }
void writeBuzzer(bool on) { digitalWrite(BUZZER_PIN, BUZZER_ACTIVE_HIGH ? (on ? HIGH : LOW) : (on ? LOW : HIGH)); }

void beep(uint8_t count, uint16_t onMs, uint16_t offMs) {
  for (uint8_t i = 0; i < count; i++) { writeBuzzer(true); delay(onMs); writeBuzzer(false); if (i + 1 < count) delay(offMs); }
}

void indicateSuccess() { writeLed(LED_GREEN_PIN, true); writeLed(LED_RED_PIN, false); beep(1, 35, 20); }
void indicateRejected() { writeLed(LED_RED_PIN, true); beep(2, 120, 60); }
void indicateQueued() { writeLed(LED_GREEN_PIN, true); beep(1, 45, 20); }

// ===== WEB HANDLERS =====
void handleRoot() {
  String html = R"HTML(
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TrackPigeon ETS</title>
<style>body{margin:0;background:#0f172a;color:#e5e7eb;font-family:Arial,sans-serif}main{max-width:720px;margin:0 auto;padding:20px}h1{font-size:24px;margin:0 0 4px}.sub{color:#94a3b8;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.card{border:1px solid #263247;background:#111827;border-radius:8px;padding:12px}.label{color:#94a3b8;font-size:12px;text-transform:uppercase}.value{font-size:19px;font-weight:700;margin-top:4px}.tag{font-family:monospace;color:#38bdf8;font-size:30px;letter-spacing:1px}.ok{color:#22c55e}.warn{color:#eab308}.bad{color:#ef4444}</style></head>
<body><main><h1>TrackPigeon ETS</h1><p class="sub">Single RDM6300 | 100ms | GPS</p>
<div class="card"><div class="label">Last RFID</div><div id="lastRFID" class="value tag">-</div><div id="lastTime" class="sub">-</div><div id="lastStatus">-</div></div>
<div class="grid" style="margin-top:10px">
<div class="card"><div class="label">WiFi</div><div id="wifi" class="value">-</div></div>
<div class="card"><div class="label">Server</div><div id="server" class="value">-</div></div>
<div class="card"><div class="label">GPS</div><div id="gps" class="value">-</div><div id="coords" class="sub">-</div></div>
<div class="card"><div class="label">Queue</div><div id="queue" class="value">0</div></div>
<div class="card"><div class="label">Success</div><div id="success" class="value ok">0</div></div>
<div class="card"><div class="label">Rejected</div><div id="rejected" class="value bad">0</div></div>
</div></main>
<script>async function refresh(){try{const r=await fetch('/status');const d=await r.json();lastRFID.textContent=d.last_rfid;lastTime.textContent=d.last_time;lastStatus.textContent=d.last_status;wifi.textContent=d.wifi;server.textContent=d.server;gps.textContent=d.gps;coords.textContent=Number(d.lat).toFixed(6)+', '+Number(d.lng).toFixed(6);queue.textContent=d.queue_count;success.textContent=d.success_count;rejected.textContent=d.rejected_count}catch(e){}}
setInterval(refresh,1000);refresh();</script></body></html>
)HTML";
  web.send(200, "text/html", html);
}

void handleStatus() {
  StaticJsonDocument<512> doc;
  doc["wifi"] = wifiStatus; doc["server"] = serverStatus;
  doc["gps"] = gpsStatus; doc["lat"] = gpsLat; doc["lng"] = gpsLng;
  doc["last_rfid"] = lastRFID; doc["last_time"] = lastTime; doc["last_status"] = lastStatus;
  doc["queue_count"] = queueCount; doc["success_count"] = successCount;
  doc["rejected_count"] = rejectedCount;
  String json; serializeJson(doc, json);
  web.send(200, "application/json", json);
}