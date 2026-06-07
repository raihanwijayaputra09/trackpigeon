Proyek Electronic Timing System (ETS) ini menggunakan mikrokontroler Wemos Lolin S2 Mini sebagai 
pusat kendali, yang terhubung dengan komponen seperti RFID Reader RDM6300, GPS GY-NEO6MV2, dan 
RTC DS3231 untuk mencatat identitas, lokasi, dan waktu kedatangan merpati secara otomatis. Komunikasi antar 
komponen menggunakan UART dan I2C melalui pin GPIO Wemos. Sistem juga dilengkapi buzzer sebagai 
notifikasi suara, rocker switch untuk daya, tactile switch sebagai reset, serta tiga LED indikator (merah, kuning, 
biru) untuk status sistem. Semua komponen disuplai daya dari sumber utama, memungkinkan sistem bekerja 
efisien baik secara online maupun offline.