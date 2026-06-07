# PRD — Rebuild & Scale Merpatools Community Startup Platform

## Product Name

Merpatools Community Platform

## Vision

Membangun platform digital komunitas merpati balap modern berbasis web + IoT yang dapat digunakan oleh penghobi, komunitas latihan, panitia lomba, breeder, dan pemilik kandang untuk:

* Tracking latihan & lomba merpati
* Live race monitoring
* RFID / ETS integration
* Ranking komunitas
* Analitik performa burung
* Marketplace komunitas
* Social community platform
* Monetisasi startup komunitas

---

# 1. Analisis Web App Existing

## Teknologi Existing

Berdasarkan file .zip:

### Backend

* PHP Native
* MySQL
* REST-like API sederhana
* Session authentication

### Frontend

* HTML + CSS + Vanilla JavaScript
* Leaflet.js (map)
* OpenStreetMap
* Lucide Icons

### Existing Features

#### User & Authentication

* Login
* Register
* Google Login support
* Session user

#### Bird Management

* CRUD data burung
* Foto burung
* RFID tag
* Status burung
* Statistik performa

#### Training / Race

* Input titik lepas
* Tracking jarak
* Live monitoring
* Ranking kecepatan
* Arrival timing
* Public ranking

#### ETS / RFID

* ETS check-in API
* Firmware download endpoint
* Live check-in system

#### Geo Features

* GPS kandang
* GPS titik lepas
* Leaflet map picker
* Distance calculation

#### Dashboard

* Live race page
* Statistik latihan
* Leaderboard

---

# 2. Masalah Pada Existing App

## Technical Problems

### Architecture

* Monolithic PHP
* Sulit di-scale
* Tidak modular
* Tidak maintainable untuk startup besar

### Security

* Credentials hardcoded di config
* Tidak ada JWT
* Tidak ada rate limit
* Belum ada role management
* Belum ada audit logging

### Database

* Relasi belum optimal
* Tidak ada indexing strategy lengkap
* Belum scalable
* Tidak ada migration system

### Frontend

* Vanilla JS sulit berkembang
* Tidak reactive
* UI belum mobile-first optimal
* Tidak ada component system

### API

* Belum RESTful proper
* Tidak ada API versioning
* Tidak ada websocket realtime

### Infrastructure

* Belum containerized penuh
* Belum CI/CD
* Belum monitoring
* Belum backup strategy

---

# 3. Target Startup Direction

## Positioning

Platform komunitas merpati balap modern Indonesia.

## Target User

### Primary

* Penghobi merpati balap
* Pemilik kandang
* Komunitas merpati
* Panitia lomba

### Secondary

* Breeder
* Seller pakan
* Toko aksesoris
* Veterinarian burung
* Sponsor event

---

# 4. Rebuild Architecture Recommendation

## Recommended Tech Stack

### Frontend

#### Main Stack

* Next.js 15
* React
* TypeScript
* TailwindCSS
* ShadCN UI
* Framer Motion

#### State Management

* Zustand
* TanStack Query

#### Realtime

* Socket.IO Client
* WebSocket

#### Maps

* Mapbox atau Leaflet

---

### Backend

#### API

* NestJS
  ATAU
* Express.js + TypeScript

#### Auth

* JWT
* Refresh Token
* OAuth Google
* Role-based Access

#### ORM

* Prisma ORM

#### Database

* PostgreSQL

#### Realtime

* Socket.IO
* Redis Pub/Sub

#### Queue

* BullMQ

---

### Infrastructure

#### Container

* Docker
* Docker Compose

#### Reverse Proxy

* Nginx Proxy Manager

#### Deployment

* VPS
* CasaOS
* Coolify
* Railway / Render (testing)

#### Monitoring

* Grafana
* Prometheus
* Uptime Kuma

#### CI/CD

* GitHub Actions

---

# 5. Product Roadmap

# Phase 1 — MVP Rebuild

## Goal

Membuat ulang sistem existing dengan code modern.

## Features

### Authentication

* Login
* Register
* Google Login
* Forgot password
* Email verification

### Dashboard

* Overview statistik
* Bird summary
* Recent activity
* Live race card

### Bird Management

* CRUD burung
* Upload foto
* RFID binding
* Status management
* Pedigree basic

### Race Management

* Create race
* Release point
* Distance calculation
* Live monitoring
* Ranking otomatis

### ETS Integration

* RFID check-in
* ESP32 support
* API device token
* Device registration

### Public Pages

* Public leaderboard
* Public bird profile
* Public race result

---

# Phase 2 — Community Platform

## Goal

Menjadikan platform sosial komunitas.

## Features

### Community

* Club system
* Community page
* Event page
* Community feed

### Social

* Postingan latihan
* Foto/video upload
* Like/comment
* Follow user

### Tournament

* Tournament bracket
* Race schedule
* Auto result
* Prize tracking

### Notification

* Push notification
* WhatsApp integration
* Telegram bot

---

# Phase 3 — Startup Monetization

## Goal

Mulai menghasilkan revenue.

## Features

### Subscription

* Premium account
* Club subscription
* Event organizer plan

### Marketplace

* Jual burung
* Pakan
* Obat
* ETS device
* Accessories

### Ads

* Sponsor banner
* Featured birds
* Featured race

### Analytics Premium

* AI performance prediction
* Historical analytics
* Health monitoring

---

# 6. Detailed Core Features

# 6.1 Authentication System

## User Roles

### Super Admin

* Full access

### Community Admin

* Kelola komunitas
* Kelola event

### Member

* Kelola kandang
* Kelola burung
* Ikut event

### Device

* ETS hardware access only

---

## Auth Flow

### Standard Login

1. User input email/password
2. Backend validate
3. JWT access token
4. Refresh token
5. Secure session

### Google Login

1. OAuth redirect
2. Verify Google token
3. Create account if needed
4. Login session

---

# 6.2 Bird Management System

## Bird Entity

### Required Fields

* Ring number
* Bird name
* Gender
* Color
* Birth date
* Owner

### Optional Fields

* Photo
* RFID tag
* Bloodline
* Parents
* Weight
* Notes

---

## Bird Analytics

### Metrics

* Average speed
* Win rate
* Arrival consistency
* Distance performance
* Historical graph

### Charts

* Speed trend
* Monthly performance
* Distance comparison
* Community ranking

---

# 6.3 Race System

## Race Creation

### Inputs

* Race name
* Release location
* GPS coordinate
* Release time
* Bird participants
* Visibility

### Auto Calculations

* Distance
* ETA
* Ranking
* Speed coefficient

---

## Live Race

### Features

* Live leaderboard
* Real-time arrival
* Auto refresh
* Spectator page
* Public live link

### Realtime Architecture

ETS Device
↓
API Gateway
↓
Redis Queue
↓
Database
↓
Socket.IO
↓
Frontend Live Dashboard

---

# 6.4 ETS / IoT Integration

## Hardware

### Supported Device

* ESP32
* RFID RC522
* Custom ETS

### Device Features

* Offline queue
* Auto reconnect
* Secure API key
* Firmware update

---

## Device API

### Required Endpoints

* Device auth
* Check-in bird
* Sync offline data
* Device status
* Firmware update

---

# 6.5 Community Feature

## Club System

### Features

* Club profile
* Member management
* Club ranking
* Club events

---

## Feed System

### Content

* Race updates
* Bird showcase
* Community discussion
* Marketplace post

---

# 7. UI/UX Direction

## Design Style

### Style

* Modern dark/light
* Racing dashboard aesthetic
* Mobile-first
* Fast navigation

### Inspirations

* Strava
* Formula 1 dashboard
* Live sports platform
* Discord community

---

## Main Pages

### Landing Page

* Hero section
* Community stats
* Live race preview
* Download app CTA

### Dashboard

* Bird summary
* Active races
* Community activity
* Performance chart

### Live Race

* Full realtime table
* Arrival animation
* Live map
* Spectator mode

---

# 8. Database Architecture

## Main Tables

### Users

* users
* user_sessions
* user_roles

### Birds

* birds
* bird_stats
* bird_history
* bird_pedigree

### Races

* races
* race_participants
* race_results

### Community

* clubs
* club_members
* posts
* comments

### IoT

* devices
* device_logs
* ets_checkins

---

# 9. API Design

## API Versioning

/api/v1/

---

## Main APIs

### Auth

* POST /auth/login
* POST /auth/register
* POST /auth/refresh

### Birds

* GET /birds
* POST /birds
* PUT /birds/:id
* DELETE /birds/:id

### Races

* POST /races
* GET /races/live
* GET /races/:id

### ETS

* POST /ets/checkin
* POST /ets/sync

---

# 10. Security Requirements

## Authentication

* JWT
* Refresh token rotation
* Secure cookie
* CSRF protection

## API Security

* Rate limiting
* API key device
* IP logging
* Audit logs

## Infrastructure

* HTTPS
* Cloudflare
* Fail2Ban
* Firewall

---

# 11. Scalability Requirements

## Performance Targets

### MVP

* 1,000 users
* 100 concurrent live viewers
* 20 ETS devices

### Scale Target

* 100,000 users
* 10,000 concurrent viewers
* 5,000 ETS devices

---

## Scaling Strategy

### Horizontal Scaling

* Load balancer
* Redis cache
* CDN
* Separate DB server

---

# 12. Startup Monetization Strategy

## Revenue Streams

### Subscription

* Premium analytics
* Unlimited races
* Club tools

### Marketplace Fee

* Transaction fee
* Featured listing

### Event Tools

* Paid tournament system
* Ticketing

### Hardware

* ETS hardware sales
* RFID bundle

---

# 13. Mobile App Future Plan

## Tech

* React Native
  ATAU
* Flutter

## Features

* Live notification
* Scan RFID
* Live race
* Chat community

---

# 14. AI Feature Roadmap

## AI Features

### Prediction

* Prediksi performa
* Prediksi ETA
* Ranking prediction

### Smart Analytics

* Bird health indicator
* Training recommendation
* Pattern analysis

### AI Assistant

* Race setup assistant
* Community moderation

---

# 15. DevOps & Deployment

## Development Flow

### Local

* Docker Compose
* PostgreSQL
* Redis

### Staging

* VPS testing
* CI/CD auto deploy

### Production

* Multi-server VPS
* Backup automation
* Monitoring

---

# 16. Suggested Folder Structure

## Frontend

/apps/web
/components
/features
/hooks
/lib
/services
/store
/types

---

## Backend

/apps/api
/modules
/auth
/users
/birds
/races
/ets
/community
/shared

---

# 17. Suggested Development Timeline

## Month 1

* System planning
* UI design
* Database design
* Setup infra

## Month 2

* Authentication
* User dashboard
* Bird management

## Month 3

* Race system
* Live race
* Realtime socket

## Month 4

* ETS integration
* Community feature
* Optimization

## Month 5

* Marketplace
* Payment
* AI analytics basic

## Month 6

* Launch beta komunitas

---

# 18. Recommended AI Workflow For Development

## Workflow

### Step 1 — Planning

* Generate PRD
* Generate DB schema
* Generate UI mockup

### Step 2 — Architecture

* Setup monorepo
* Setup backend
* Setup frontend

### Step 3 — AI Coding Agent

Gunakan:

* Claude Code
* OpenCode
* Cursor
* RooCode
* Cline

### Step 4 — Deployment

Deploy ke:

* STB Armbian + CasaOS
* VPS production

---

# 19. Recommended Immediate Next Steps

## Priority 1

* Rebuild backend modern
* Rebuild frontend modern
* Secure auth
* Modular architecture

## Priority 2

* Realtime live race
* ETS stable integration
* Community system

## Priority 3

* Marketplace
* Monetization
* Mobile app

---

# 20. Final Startup Strategy

## Main Goal

Bukan sekadar aplikasi latihan merpati.

Tetapi:

"Platform digital komunitas merpati terbesar Indonesia."

---

## Startup Advantage

Karena niche ini:

* Sangat spesifik
* Komunitas loyal
* Sedikit kompetitor digital modern
* Bisa berkembang jadi ekosistem

---

# 21. Recommended Stack For Your Current Hardware

Karena kamu menggunakan:

* STB Armbian
* CasaOS
* Self-hosted server

Maka cocok untuk:

## Development Stack

* Docker Compose
* PostgreSQL
* Redis
* Next.js
* NestJS
* Nginx Proxy Manager
* Coolify

---

# 22. Suggested Future Integrations

## Integrations

* WhatsApp API
* Midtrans
* Xendit
* Firebase notification
* Telegram Bot
* Google Maps
* MQTT broker

---

# 23. Success Metrics

## MVP Metrics

* 100 komunitas aktif
* 1,000 user
* 50 race/minggu

## Growth Metrics

* Monthly active users
* Daily race activity
* Marketplace GMV
* Device activation

---

# 24. Conclusion

Project existing sudah sangat bagus sebagai proof-of-concept.

Yang paling kuat:

* Konsep ETS
* Live race
* RFID integration
* Community potential

Tetapi untuk dijadikan startup besar:

* Harus rebuild modern
* Modular
* Secure
* Scalable
* Realtime-ready
* Mobile-first

Dengan niche yang spesifik dan komunitas yang loyal, project ini punya potensi besar menjadi:

"Strava + Community + Marketplace untuk dunia merpati balap Indonesia."
