# System Diagrams

Mermaid diagrams for the application architecture, deployment, account workflows, complaint handling, and database relationships.

## Application Architecture

```mermaid
flowchart LR
    User[Student, staff, or admin browser] --> Apache[Apache web server]
    Apache --> PHP[PHP application]
    PHP --> Auth[Authentication and role checks]
    PHP --> MySQL[(MySQL / MariaDB)]
    PHP --> Files[Private uploads directory]
    PHP --> Mail[SMTP mail service]
    PHP --> PDF[Dompdf reports and receipts]
    Cron[Scheduled PHP jobs] --> MySQL
    PHP --> Logs[Application logs]
```

## Install on a New Computer

```mermaid
flowchart TD
    A[Install XAMPP and Composer] --> B[Copy or clone project into web root]
    B --> C[Start Apache and MySQL]
    C --> D[Create mku_complaints database]
    D --> E[Import database/schema.sql into empty database]
    E --> F{Need demo content?}
    F -- Yes --> G[Import database/seed.sql]
    F -- No --> H[Keep database empty]
    G --> I[Configure config/config.php]
    H --> I
    I --> J[Run composer install]
    J --> K[Open APP_URL in browser]
    K --> L[Register student or log in as seeded/admin-created user]
```

## Account and Role Workflow

```mermaid
flowchart TD
    Start([Open application]) --> Choice{Account type}
    Choice -- Student --> Register[Student self-registers]
    Register --> StudentSession[Account created and signed in]
    StudentSession --> StudentProfile[Student profile]
    Choice -- Staff or admin --> Provision[Admin creates account]
    Provision --> Role[Admin assigns role and, for staff, department]
    Role --> Login[User signs in]
    StudentProfile --> Login
    Login --> Check{Account active and credentials valid?}
    Check -- No --> Denied[Show login error or lockout]
    Check -- Yes --> Dispatch{Role}
    Dispatch -- Student --> StudentDash[Student dashboard]
    Dispatch -- Staff --> StaffDash[Department staff dashboard]
    Dispatch -- Admin --> AdminDash[Admin dashboard]
    StudentDash --> Profile[Role-specific profile]
    StaffDash --> Profile
    AdminDash --> Profile
```

## Complaint Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Submitted: Student submits complaint
    Submitted --> UnderReview: Admin or staff starts review
    UnderReview --> InProgress: Work begins
    InProgress --> Resolved: Resolution recorded
    Resolved --> Closed: Complaint closed
    Submitted --> Rejected: Complaint rejected with reason
    UnderReview --> Rejected: Complaint rejected with reason
    InProgress --> Rejected: Complaint rejected with reason
    Submitted --> Escalated: No action for more than seven days
    UnderReview --> Escalated: No action for more than seven days
    InProgress --> Escalated: No action for more than seven days
    Escalated --> UnderReview: Review resumes
    Resolved --> [*]
    Closed --> [*]
    Rejected --> [*]
```

## Complaint Handling Sequence

```mermaid
sequenceDiagram
    actor Student
    participant App as PHP application
    participant DB as MySQL database
    actor Admin
    actor Staff

    Student->>App: Submit complaint, choose department, add optional attachments
    App->>DB: Save complaint, department, tracking ID, and initial status
    App->>DB: Notify active staff in selected department and administrators
    App-->>Student: Show confirmation and tracking ID
    Staff->>App: Review complaint and update status or reply
    App->>DB: Save status history and response
    App->>DB: Create student notification for public update
    Student->>App: View update, reply, or rate resolution
    Admin->>App: Review all complaints or reassign department
    App->>DB: Save reassignment
```

## Entity Relationships

```mermaid
erDiagram
    DEPARTMENTS ||--o{ USERS : employs
    USERS ||--o{ COMPLAINTS : submits
    DEPARTMENTS o|--o{ COMPLAINTS : receives
    USERS o|--o{ COMPLAINTS : assigned_to
    COMPLAINTS ||--o{ COMPLAINT_STATUS_HISTORY : records
    USERS ||--o{ COMPLAINT_STATUS_HISTORY : changes
    COMPLAINTS ||--o{ ATTACHMENTS : includes
    COMPLAINTS ||--o{ RESPONSES : has
    USERS ||--o{ RESPONSES : writes
    USERS ||--o{ FEEDBACK : submits
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ PASSWORD_RESETS : requests

    DEPARTMENTS {
        int id PK
        string name
        string email
    }
    USERS {
        int id PK
        string full_name
        string email UK
        string role
        int department_id FK
    }
    COMPLAINTS {
        int id PK
        string tracking_id UK
        int student_id FK
        int department_id FK
        int assigned_to FK
        string status
    }
    COMPLAINT_STATUS_HISTORY {
        int id PK
        int complaint_id FK
        int changed_by FK
        string new_status
    }
    ATTACHMENTS {
        int id PK
        int complaint_id FK
        string stored_name
    }
    RESPONSES {
        int id PK
        int complaint_id FK
        int author_id FK
        boolean is_internal
    }
    FEEDBACK {
        int id PK
        int student_id FK
        string type
        int rating
    }
    NOTIFICATIONS {
        int id PK
        int user_id FK
        boolean is_read
    }
    PASSWORD_RESETS {
        int id PK
        int user_id FK
        string token_hash
    }
```