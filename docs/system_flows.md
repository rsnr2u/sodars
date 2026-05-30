# SODARS Complete System Flow Chart & Operational Directory
## Streamline Outdoor Advertising Reach Solutions

This document establishes the official visual and functional workflow diagrams for all transactional, geographical, and programmatic pipelines within the SODARS platform.

---

## 1. 🌐 Complete Platform Flow
This end-to-end flowchart maps the operational lifecycle of a booking from search to commission payouts:

```mermaid
graph TD
    Cust[Customer / Advertiser Visits Website] --> Search[Search Inventory by City/Area/Road]
    Search --> View[View Inventory Details Map/Pricing]
    View --> Inq[Create Campaign Inquiry]
    Inq --> Camp[Campaign Created & Holds Placed]
    Camp --> Book[Booking Request Generated]
    Book --> AdminReview[Admin Portal Reviews Booking]
    AdminReview --> ProvSend[Provider Approval Request Sent]
    ProvSend --> ProvCheck{Provider Approves?}
    ProvCheck -->|No - Rejects| Release[Holds Released & Cancelled]
    ProvCheck -->|Yes - Approves| Reserve[Inventory Formally Reserved]
    Reserve --> Block[Availability Calendar Blocked]
    Block --> Invoice[GST Invoice Generated]
    Invoice --> Pay[Customer Completes Payment]
    Pay --> Active[Campaign Activated & Mounted]
    Active --> Upload[Advertiser Uploads Artworks]
    Upload --> Install[Provider Installation Completed]
    Install --> Proof[Geotagged Photo Proof Uploaded]
    Proof --> Running[Campaign Active & Running]
    Running --> Complete[Campaign Completed]
    Complete --> Settle[Provider Payout Settlement Created]
    Complete --> Comm[Agent Sales Commission Released]
    Settle --> Analytics[Reports & Dashboards Updated]
    Comm --> Analytics

    style Cust fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style ProvCheck fill:#fff3e0,stroke:#fb8c00,stroke-width:2px;
    style Active fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
    style Complete fill:#f3e5f5,stroke:#8e24aa,stroke-width:2px;
    style Analytics fill:#e0f7fa,stroke:#00acc1,stroke-width:2px;
```

---

## 2. 🖥️ Decoupled Portal Flow
An architecture diagram illustrating how actions are partitioned across the four main user front-ends:

```mermaid
graph TD
    Web[Marketplace Website<br/>www.sodars.com] -->|Activities| WebAct[Inventory Search<br/>Campaign Inquiry<br/>Lead Generation<br/>Marketplace Browsing]
    
    Admin[Admin Portal<br/>admin.sodars.com] -->|Activities| AdminAct[Booking Engine Control<br/>Campaign Approvals<br/>GST Invoices & Finance<br/>Provider Verification<br/>Operations Analytics]
    
    Biz[Business Portal<br/>business.sodars.com] -->|Activities| BizAct[Inventory Management<br/>Booking Approvals<br/>Availability Calendars<br/>Artwork & Proof Uploads<br/>Payout Ledger Withdrawals]
    
    Agent[Agents Portal<br/>agents.sodars.com] -->|Activities| AgentAct[CRM Leads Tracker<br/>Campaign Structuring<br/>Booking Assistance<br/>Commission Accounts]

    style Web fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style Admin fill:#ede7f6,stroke:#5e35b1,stroke-width:2px;
    style Biz fill:#f3e5f5,stroke:#8e24aa,stroke-width:2px;
    style Agent fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 3. 📍 Geospatial Location Flow
The hierarchical taxonomy organizing all physical advertising media:

```mermaid
graph TD
    Country[Country Table] -->|1:N| State[State Table]
    State -->|1:N| District[District Table]
    District -->|1:N| City[City Table]
    City -->|1:N| Area[Area Table]
    Area -->|1:N| Landmark[Landmark Table]
    Area -->|1:N| Road[Road Table]
    Road -->|1:N| Inventory[Hoardings / LED Inventory]

    style Country fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style City fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
    style Inventory fill:#ffebee,stroke:#c62828,stroke-width:2px;
```

---

## 4. 👥 Provider Onboarding Flow
The operational lifecycle verifying third-party media vendors:

```mermaid
graph TD
    Reg[Provider Registers Profile] --> Verify[Admin Verifies Documents]
    Verify --> KYC{Validate PAN/GSTIN?}
    KYC -->|Invalid| Reject[Registration Rejected]
    KYC -->|Valid| Approve[Provider Approved]
    Approve --> Add[Provider Adds Inventory]
    Add --> InvVerify{Admin Verifies Board Specs?}
    InvVerify -->|Invalid| Add
    InvVerify -->|Valid| Enable[Marketplace Listing Enabled]
    Enable --> Live[Inventory Published Live]

    style Reg fill:#ede7f6,stroke:#5e35b1,stroke-width:2px;
    style KYC fill:#fff3e0,stroke:#fb8c00,stroke-width:2px;
    style Live fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 5. 📦 Inventory Ingestion Flow
The steps taken by a provider to publish a hoarding or LED display listing:

```mermaid
graph TD
    Start[Provider Onboarding] --> Add[Click Add Inventory]
    Add --> Meta[Enter Specs: Dimensions, Direction, Illumination]
    Meta --> Upload[Upload Mockup Images & Drone Clips]
    Upload --> Geotag[Pin GPS Coordinates on Map]
    Geotag --> Pricing[Set Base, Weekly & Seasonal Rates]
    Pricing --> Availability[Set Available/Blocked Calendars]
    Availability --> Toggle[Toggle Enable Marketplace]
    Toggle --> Live[Listing Live for Search Queries]

    style Start fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
    style Live fill:#ffebee,stroke:#c62828,stroke-width:2px;
```

---

## 6. 🚀 Campaign Structuring Flow
How media buyers aggregate multiple billboard holdings into a single execution campaign:

```mermaid
graph TD
    Inq[Customer Enters City/Budget Goals] --> Create[Campaign Created]
    Create --> Map[Select Multiple Hoardings from Route Map]
    Map --> Book[Generates Individual Booking Holds]
    Book --> Approvals[Routed for Provider Approvals]
    Approvals --> Holds[Dates Reserved on Booking Calendar]
    Holds --> Pay[Invoice Paid & Activated]
    Pay --> Active[Campaign Running]
    Active --> Complete[Campaign Ended]

    style Inq fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style Active fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 7. ⏱️ Core Booking Engine Flow
> [!IMPORTANT]
> **Primary Platform Transaction Engine**: Resolves date reservations, runs availability calculations, processes billing, and completes settlements.

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Portal Client (React/Blade)
    participant API as Booking Engine (/bookings)
    participant Lock as Redis Atomic Lock
    participant DB as Database (sodars_db)
    
    Buyer->>API: POST /bookings (inventory_id, start_date, end_date)
    API->>Lock: Request transactional hold on dates
    alt Overlap / Conflict Detected
        Lock-->>API: Slot Reserved
        API-->>Buyer: 422 Unprocessable (Conflict Alert)
    else Slot Clear
        Lock->>DB: Insert booking_calendar record (status: Reserved)
        DB-->>Lock: Success
        API-->>Buyer: 201 Created (booking_id, status: Reserved)
    end
    
    Note over API, DB: Awaiting Approvals & Checkout
    API->>DB: Update booking_status to PendingApproval
    DB-->>API: Success
    Buyer->>API: Settle GST Invoice (Stripe/Razorpay checkout)
    API->>DB: Mark status Confirmed & Payment Status Paid
    API->>DB: Generate GST Invoices & trigger settlements splits
```

---

## 8. 🛡️ Availability Engine Flow
The conflict checking rules verifying dates before booking creations:

```mermaid
graph TD
    Select[Hoarding Selected for Target Dates] --> ActiveCheck{Check Active Bookings?}
    ActiveCheck -->|Overlap| Conflict[Date Conflict Alert - Terminate]
    ActiveCheck -->|Clear| ReserveCheck{Check Active Cart Holds?}
    ReserveCheck -->|Overlap| Conflict
    ReserveCheck -->|Clear| MaintenanceCheck{Check Maintenance Dates?}
    MaintenanceCheck -->|Overlap| Conflict
    MaintenanceCheck -->|Clear| BlockCheck{Check Owner Manual Blocks?}
    BlockCheck -->|Overlap| Conflict
    BlockCheck -->|Clear| Confirm[Verify Availability -> Acquire Lock]

    style Select fill:#ede7f6,stroke:#5e35b1,stroke-width:2px;
    style Conflict fill:#ffebee,stroke:#c62828,stroke-width:2px;
    style Confirm fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 9. 💵 Finance & Settlement Flow
The accounting pipeline mapping billing balances to payouts and commissions:

```mermaid
graph TD
    Confirm[Booking Confirmed] --> Invoice[Generate Invoice PDF]
    Invoice --> Tax[Compute CGST/SGST Tax Matrices]
    Tax --> Pay[Process Customer Transaction]
    Pay --> Record[Record Gross Platform Revenue]
    Record --> Split{Split Commission Accounts}
    Split --> Payout[Create Provider Payout Ledger <br/> Deduct TDS & Platforms Split]
    Split --> Comm[Calculate Sales Agent Commissions]
    Payout --> Transfer[Disburse Balance to Vendor Account]
    Comm --> Release[Disburse Agent Commissions Payout]

    style Confirm fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
    style Split fill:#fff3e0,stroke:#fb8c00,stroke-width:2px;
    style Transfer fill:#e0f7fa,stroke:#00acc1,stroke-width:2px;
    style Release fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
```

---

## 10. 👥 CRM Sales Agent Flow
The sales pipeline tracking prospects from lead capture to payouts:

```mermaid
graph TD
    Login[Sales Agent Logins] --> Lead[Ingest Lead details]
    Lead --> CRM[Customer CRM Follow-up scheduled]
    CRM --> Proposal[Create Campaign Proposal]
    Proposal --> Check[Verify Availability & Block Slots]
    Check --> Close[Confirm Booking Payment]
    Close --> Complete[Mark Campaign Completed]
    Complete --> Comm[Commission Generated & Disbursed]

    style Login fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style Comm fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 11. 🌐 Central API Architecture Flow
The routing and execution layers of the centralized API engine:

```mermaid
graph TD
    Front[Frontend Client portals] -->|Axios HTTPS Request| Routing[Laravel Routing Engine]
    Routing -->|Sanctum Authentication Check| Auth{Auth Valid?}
    Auth -->|No| Unauthorized[Return 401 Unauthorized]
    Auth -->|Yes| Logic[Execute Controller Domain logic]
    Logic -->|Verify Spatie Gate| Spatie{Lacks Permissions?}
    Spatie -->|Yes| Forbidden[Return 403 Forbidden]
    Spatie -->|No| Process[Interact with Database Repositories]
    Process --> Response[Structure JSON Response envelope]
    Response --> Front

    style Front fill:#e3f2fd,stroke:#1e88e5,stroke-width:2px;
    style Spatie fill:#fff3e0,stroke:#fb8c00,stroke-width:2px;
    style Response fill:#e8f5e9,stroke:#43a047,stroke-width:2px;
```

---

## 12. ☁️ Cloud File Ingestion Flow
The pipeline optimizing media uploads and storing paths in the database:

```mermaid
graph TD
    Upload[User Uploads Image/Video] --> Validate[Verify File size & Dimension ratios]
    Validate --> Compress[Compress File to WebP format]
    Compress --> AWS[Upload to AWS S3 / Cloudflare R2 bucket]
    AWS --> URL[Get Public CDN URL Link]
    URL --> DB[Store CDN URL inside Database log]

    style Upload fill:#f3e5f5,stroke:#8e24aa,stroke-width:2px;
    style DB fill:#e0f7fa,stroke:#00acc1,stroke-width:2px;
```

---

## 13. 🔔 Event-Driven Notification Queue Flow
How async system alerts are managed without blocking browser sessions:

```mermaid
graph TD
    Trigger[Event Triggered: e.g. BookingConfirmed] --> Queue[Dispatch Job to Redis Queue]
    Queue --> Worker[Supervisor Redis Worker threads]
    Worker --> Route{Route Channels}
    Route --> SMTP[Send Transactional Email via SMTP]
    Route --> SMS[Send Twilio SMS Alert]
    Route --> WA[Send Twilio WhatsApp Message]
    Route --> Push[Send Firebase Web/Mobile Push Notification]

    style Trigger fill:#fff3e0,stroke:#fb8c00,stroke-width:2px;
    style Worker fill:#ede7f6,stroke:#5e35b1,stroke-width:2px;
```

---

## 14. 🔄 Complete Business Lifecycle Loop
The complete operational loop linking all modules:

```mermaid
graph LR
    Add[1. Provider Adds Inventory] --> Live[2. Active on Marketplace]
    Live --> Search[3. Customer Searches Inventory]
    Search --> Inq[4. Submits Inquiry]
    Inq --> Book[5. Booking Request Created]
    Book --> Approve[6. Provider Approves Hold]
    Approve --> Pay[7. Payment Settled]
    Pay --> Active[8. Campaign Activated]
    Active --> Proof[9. Mounting Proof Uploaded]
    Proof --> Complete[10. Campaign Ended]
    Complete --> Settle[11. Payout Settlement Released]
    Settle --> Analytics[12. System Analytics Updated]
    Analytics --> Add
```

---

## 15. 🏆 Final Enterprise Platform Blueprint
How all specialized systems compile into the single **SODARS ERP Marketplace**:

```
+-------------------------------------------------------------------------+
|                        SODARS Enterprise ERP                            |
+-------------------------------------------------------------------------+
|  [Marketplace Website]    [Geo-Intelligence]    [Booking Engine]        |
|  - Discovery Frontend     - Google Maps Pins    - Atomic Holds locks    |
|  - SEO Traffic Generation - Proximity scoring   - Conflict checking     |
+-------------------------------------------------------------------------+
|  [Availability Engine]    [Provider Portal]     [Finance System]        |
|  - Date availability scan - Inventory CRUD      - GST tax calculators   |
|  - Maintenance logs       - Verification checks - Payout ledgers        |
+-------------------------------------------------------------------------+
|  [CRM Sales Module]       [System Analytics]    [Notification Queue]    |
|  - Leads kanban pipeline  - Cashflow MRR graphs - Twilio SMS/WhatsApp   |
|  - Follow-up reminders    - Occupancy reports   - SMTP Email templates  |
+-------------------------------------------------------------------------+
```
