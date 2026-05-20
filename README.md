# 🎪 UKMSphere - Student Club Event Promotional System

**UKMSphere** is a web-based event management platform designed for **Universiti Kebangsaan Malaysia (UKM)**. It centralizes event discovery, registration, and feedback for students, clubs, and HEP administrators.

Built as a group project for **TTTH2304 Software Design for Multimedia Systems** by Team **ABELITIES**.

---

## 🎥 Project Demo Video

[![UKMSphere Demo Video](https://drive.google.com/file/d/1hXPTgzzTc9pnCvbPZjP6hpS4e48HJZVY/view?usp=sharing)

> *Click the thumbnail above to watch the full demo video*

**Watch directly:** [UKMSphere Demo on Google Drive](https://drive.google.com/drive/folders/1kQ7sV1SjxEOQSLzCzauTVDuQ2z95Bs1m?usp=sharing)

---

## ✨ Key Features

### 🔐 Authentication System
- Secure **Login** with email/password verification
- **Role-based Registration** for three user types:
  - 👨‍🎓 **Student** - Matric number validation (starts with 'A')
  - 🏛️ **Club Organizer** - Club ID validation (starts with 'C')
  - 📊 **HEP Admin** - Work ID validation (starts with 'K')
- Password hashing for security
- Session management with persistent login

---

### 👨‍🎓 Student Features

#### Event Discovery
- Browse all campus events in a **visual card grid layout**
- **Category filters** - Academic, Sports, Cultural, Social, Workshop, Competition
- **Search events** by title or keyword
- **Real-time event status** badges (Upcoming, Today, Ongoing, Finished)
- Automatic hiding of past events from active feed

#### Event Registration
- One-click registration with email notifications
- **Participant limit tracking** (visual progress bar)
- Automatic check for duplicate registrations
- Registration deadline enforcement (based on event date/time)

#### My Events Dashboard
- View all **registered events** (Upcoming vs Past)
- **Cancel registration** with confirmation modal
- Event details with organizer information

#### Feedback System
- **Star rating** (1-5) with descriptive labels
- **Written comments** with minimum character validation
- **Photo upload** support (JPG, PNG, GIF)
- View submitted feedback with attached images

#### Messaging System
- **Contact organizer** directly from event page
- Real-time chat interface (student → organizer)
- Message history with timestamps
- Unread message indicators

---

### 🏛️ Club Organizer Features

#### Event Management
- **Create events** with:
  - Event title, description, date, time, location
  - Category selection
  - Participant limit (optional)
  - Terms & conditions
  - Event poster upload (drag & drop support)
- **Save as Draft** - Create events without publishing
- **Publish events** - Make visible to students
- **Edit events** - Update any field, replace poster
- **Delete events** - With cascade deletion of registrations

#### Event Dashboard
- View **active events** (upcoming/ongoing)
- View **ended events** (history)
- Filter by status (Show Active/Ended/All)
- Track participation progress bar

#### Participant Management
- View complete **participant list** per event
- See student names, matric numbers, phone numbers, registration time
- Export-ready participant data

#### Organizer Messaging
- **Inbox system** - View all student conversations grouped by event
- **Unread message badges** with count
- **Reply to students** in real-time chat
- Mark messages as read when viewed

#### Feedback Analytics
- View **aggregated feedback** per event
- Average rating calculation
- Individual student feedback with comments and photos
- See which students gave feedback

---

### 📊 HEP Admin Features

#### Dashboard Analytics
- **System overview** cards:
  - Total students registered
  - Active clubs count
  - Active events count
  - Pending reviews

#### Report Management
- Generate **post-event reports** for finished events
- **Executive summary** section (HEP remarks)
- **Participation data** (total registered, fill rate, avg rating)
- **Feedback log** - All student comments with ratings
- **Report status tracking** (Draft → Submitted → Reviewed)
- **Lock reports** after submission (no further edits)
- **Print/PDF export** of reports

#### Report Status Distribution
- Visual doughnut chart showing:
  - No Report (events without reports)
  - Draft reports
  - Submitted reports
  - Reviewed reports

#### Low Registration Alert
- Identify events with **low participation** (<10 registrants)
- **Inactive clubs** detection (no events in 30 days)

#### Report Completion Rate
- Progress bar showing % of past events with completed reports

---

### 🔔 Notification System

- **Email notifications** for event registrations
- **Unread message indicators** (red badges)
- **Real-time chat alerts** for new student inquiries
- **Session-based notification counts** in header

---

## 👥 User Roles & Permissions

| Feature | Student | Club Organizer | HEP Admin |
|---------|---------|----------------|-----------|
| View events | ✅ | ✅ | ✅ |
| Register for events | ✅ | ❌ | ❌ |
| Cancel registration | ✅ | ❌ | ❌ |
| Give feedback | ✅ | ❌ | ❌ |
| Create events | ❌ | ✅ | ❌ |
| Edit/Delete events | ❌ | ✅ | ❌ |
| View participants | ❌ | ✅ | ❌ |
| Message organizer | ✅ | ❌ | ❌ |
| Reply to students | ❌ | ✅ | ❌ |
| View feedback | ❌ | ✅ | ✅ |
| Generate reports | ❌ | ❌ | ✅ |
| System analytics | ❌ | ❌ | ✅ |

---

## 🛠️ Technology Stack

| Category | Technology |
|----------|------------|
| **Backend** | PHP (Native) |
| **Database** | MySQL |
| **Frontend** | HTML5, CSS3, Tailwind CSS |
| **JavaScript** | Vanilla JS, Chart.js |
| **Icons** | Font Awesome |
| **File Uploads** | Poster images, Feedback photos |
| **Time Handling** | Client-server time synchronization |

---

## 📁 Database Structure

Key tables:
- `users_abilities` - User authentication (email, password, user_type)
- `students_abilities` - Student profiles (full_name, matric_number, phone)
- `clubs_abilities` - Club profiles (club_name, club_id)
- `hep_abilities` - HEP profiles (full_name, work_id)
- `events_abilities` - Event details (title, date, location, category, poster)
- `event_registrations_abilities` - Registration records
- `feedback_abilities` - Feedback with rating, comments, photos
- `organizer_messages_abilities` - Student-organizer chat
- `reports_abilities` - Post-event reports by HEP
- `event_views_abilities` - Student view tracking

---

## 👥 Group Members (Team ABELITIES)

| Name | Matric No |
|------|-----------|
| Akmal Faheem | A201539 |
| Nur Hazrin Izzah | A201942 |
| Adlyn Dhia Syahira | A204168 |
| Abel Ngew | A202791 |

---

## 🚀 How to Run the Project

### Prerequisites
- XAMPP / WAMP / MAMP (PHP 7.4+ & MySQL)
- Web browser

### Step 1: Download the project
```bash
git clone https://github.com/hazrinizzah/UKMSphere.git
