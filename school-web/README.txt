SCHOOL WEBSITE V5
==================
Professional multi-page Bengali school website.

Files:
- index.html
- about.html
- academics.html
- faculty.html
- admission.html
- notice.html
- news.html
- gallery.html
- contact.html
- results.html
- routine.html
- syllabus.html
- calendar.html
- downloads.html
- students.html
- facilities.html
- rules.html
- style.css
- script.js

Notes:
1. Only the mobile navigation bar is sticky.
2. On mobile scroll, logo + school name animate into the sticky navy menu.
3. Drawer supports overlay click, close button, ESC, and menu-link close.
4. Bengali typography uses Noto Sans Bengali / Noto Serif Bengali.
5. Replace HS placeholder with the school's actual logo.
6. Replace placeholder campus/people/gallery visuals with actual assets.

V5.1: sticky menu, Student Login, Registration, Dashboard, Profile, Academic Single Page. Auth is demo UI only; connect a backend/database for production.


V5.3 STICKY NAV FIX
--------------------
The main navigation is now moved outside the header at runtime so CSS position:sticky is not constrained by the header height. It remains pinned to the top of the viewport while scrolling on desktop and mobile.


V5.4 HEADER BEHAVIOR
---------------------
- Desktop: the normal large header is visible at the top. Once scrolling starts, the sticky navy menu gains the compact school logo + name on the left, while the navigation links align to the right.
- Mobile: the separate large header is hidden and the compact school logo + name remain inside the sticky menu at all times. Hamburger stays on the right.
\n\nV5.6 STUDENT PORTAL EXPANSION\n- students.html Student Services landing\n- student-dashboard.html\n- student-profile.html\n- attendance.html\n- assignments.html\n- study-materials.html\n- change-password.html\n- Existing results, routine, notice, calendar, downloads are linked from the portal sidebar.\n- Authentication/data are frontend demo until connected to a real backend/database.\n
V5.7: Desktop hover dropdown + mobile accordion submenus added across the site.

V5.8 SCHOOL MANAGEMENT SYSTEM
=============================
Navigation repaired:
- Desktop submenu items now use proper flex layout and dropdowns.
- Mobile submenu parent click no longer closes the drawer.
- Mobile accordion submenus remain inside the drawer.

School Management modules:
- Online Admission
- Application Status
- Online Payment demo checkout
- Student / Teacher / Parent portals
- Admin Login and Admin Dashboard
- Student management
- Teacher management
- Admission review
- Payment / transaction reports
- Attendance
- Result entry / publishing
- Notice publishing
- Website content
- School settings

Important:
This is a frontend functional prototype. Online payment, real authentication,
file storage, SMS/email, and database-backed CRUD require a backend/API and
a real payment gateway before production use.
