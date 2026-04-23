-- ============================================================
--  setup.sql  -  CSS Dashboard  (enriched seed data)
-- ============================================================

CREATE TABLE IF NOT EXISTS Users (
    user_id    INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(180) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('executive','member') NOT NULL DEFAULT 'member',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
);

CREATE TABLE IF NOT EXISTS Events (
    event_id    INT          NOT NULL AUTO_INCREMENT,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    date        DATE         NOT NULL,
    time        TIME         NOT NULL,
    location    VARCHAR(200) NOT NULL,
    capacity    INT          NOT NULL DEFAULT 50,
    category    ENUM('academic','networking','social','workshop','competition') NOT NULL DEFAULT 'social',
    created_by  INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    FOREIGN KEY (created_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS RSVPs (
    rsvp_id   INT NOT NULL AUTO_INCREMENT,
    user_id   INT NOT NULL,
    event_id  INT NOT NULL,
    status    ENUM('interested','not_going') NOT NULL DEFAULT 'interested',
    rsvpd_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rsvp_id),
    UNIQUE KEY uq_user_event (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES Users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES Events(event_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Team_Stats (
    stat_id          INT NOT NULL AUTO_INCREMENT,
    event_id         INT NOT NULL,
    main_team        ENUM('communications','studentsupport','events','outreach','webtech') NOT NULL,
    sub_team         ENUM('design','socialmedia','academic','mentorship') DEFAULT NULL,
    contribution     TEXT,
    members_involved INT NOT NULL DEFAULT 0,
    logged_by        INT,
    logged_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (stat_id),
    FOREIGN KEY (event_id)  REFERENCES Events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES Users(user_id)   ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS Organization_Info (
    org_id      INT  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    email       VARCHAR(180),
    social_links TEXT,
    PRIMARY KEY (org_id)
);

-- ── SEED: USERS ───────────────────────────────────────────
-- All passwords are placeholder hashes. See README for how to regenerate.
-- exec123 / member123
INSERT IGNORE INTO Users (name, email, password, role) VALUES
('Saharsh Sukumar',  'exec@mcmaster.ca',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executive'),
('Micai Fordem',     'micai@mcmaster.ca',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executive'),
('Sumer Aulaks',     'sumer@mcmaster.ca',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executive'),
('Vincent Tran',     'vincent@mcmaster.ca', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executive'),
('Angad Johal',      'angad@mcmaster.ca',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executive'),
('Demo Member',      'member@mcmaster.ca',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Aisha Patel',      'patel@mcmaster.ca',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Jordan Lee',       'lee@mcmaster.ca',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Priya Sharma',     'sharma@mcmaster.ca',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Carlos Mendes',    'mendes@mcmaster.ca',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Fatima Hassan',    'hassan@mcmaster.ca',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Noah Williams',    'williams@mcmaster.ca','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Lena Kovacs',      'kovacs@mcmaster.ca',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Marcus Chen',      'chen@mcmaster.ca',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Sophie Bernard',   'bernard@mcmaster.ca', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member'),
('Ravi Nair',        'nair@mcmaster.ca',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member');

-- ── SEED: EVENTS ──────────────────────────────────────────
INSERT IGNORE INTO Events (title, description, date, time, location, capacity, category, created_by) VALUES
('Study Session: Algorithms',     'Collaborative session tackling algorithm problems for exams. Practice questions, whiteboard solving, and group discussion.',                                    '2025-04-15', '18:00:00', 'JHE 264',               40,  'academic',    1),
('Industry Networking Night',     'Meet software engineers and tech leads from local companies. Come with your resume ready and prepared to make lasting connections.',                           '2025-04-22', '17:30:00', 'CIBC Hall',              80,  'networking',  1),
('Hackathon Kickoff',             'Team formation, theme reveal, and a rapid-prototyping workshop. Build something awesome in 24 hours with your team.',                                        '2025-05-01', '10:00:00', 'IWC Atrium',             120, 'competition', 1),
('Python Workshop: Data Analysis','Hands-on pandas and matplotlib session with real datasets. Bring your laptop and come prepared to code along.',                                              '2025-05-08', '16:00:00', 'ABB 271',                35,  'workshop',    2),
('CS End-of-Semester Social',     'Celebrate the end of semester with food, games, and good company. All CS students welcome - bring a friend!',                                               '2025-04-10', '19:00:00', 'MUSC Ballroom',          200, 'social',      2),
('Resume Review Drop-In',         'One-on-one feedback from senior CS students and alumni. No appointment needed - just show up with your resume.',                                             '2025-03-28', '14:00:00', 'Mills Library Rm 203',   30,  'academic',    3),
('React & TypeScript Deep Dive',  'A workshop covering React hooks, context API, and TypeScript patterns used in production apps. Intermediate level.',                                         '2025-05-15', '17:00:00', 'ABB 165',                45,  'workshop',    5),
('AI/ML Career Panel',            'Hear from McMaster alumni working in AI and machine learning at top tech companies. Q&A session to follow.',                                                 '2025-05-20', '18:30:00', 'JHE 376',                60,  'networking',  1),
('Intro to Linux & Git',          'Beginner-friendly workshop covering terminal basics, file systems, Git workflow, and GitHub. Perfect for first-year students.',                              '2025-04-28', '15:00:00', 'ITB 236',                50,  'workshop',    4),
('Spring Games Night',            'Competitive gaming tournament featuring League of Legends, Chess, and party games. Prizes for top finishers in each category.',                             '2025-05-05', '18:00:00', 'MUSC Room 212',          80,  'social',      2),
('Intern Interview Bootcamp',     'Mock technical interviews with real interview questions from Google, Amazon, and Microsoft. Get matched with a senior student interviewer.',                 '2025-04-25', '13:00:00', 'Burke Science Bldg 116', 40,  'academic',    3),
('CSS Annual Banquet',            'Our year-end celebration! Recognizing member achievements, executive handoff, and a chance to connect with the broader CS community over dinner.',           '2025-06-05', '18:00:00', 'The Silhouette',         150, 'social',      1);

-- ── SEED: RSVPs ───────────────────────────────────────────
INSERT IGNORE INTO RSVPs (user_id, event_id, status) VALUES
(2,1,'interested'),(2,3,'interested'),(2,7,'interested'),
(3,3,'interested'),(3,11,'interested'),(3,12,'interested'),
(4,2,'interested'),(4,9,'interested'),(4,11,'interested'),
(5,4,'interested'),(5,7,'interested'),(5,9,'interested'),
(6,1,'interested'),(6,2,'interested'),(6,5,'interested'),(6,10,'interested'),
(7,1,'interested'),(7,3,'interested'),(7,7,'interested'),(7,11,'interested'),(7,12,'interested'),
(8,2,'interested'),(8,5,'interested'),(8,8,'interested'),(8,9,'interested'),
(9,1,'interested'),(9,4,'interested'),(9,6,'interested'),(9,11,'interested'),
(10,3,'interested'),(10,5,'interested'),(10,10,'interested'),(10,12,'interested'),
(11,2,'interested'),(11,7,'interested'),(11,8,'interested'),
(12,1,'interested'),(12,3,'interested'),(12,9,'interested'),(12,10,'interested'),
(13,4,'interested'),(13,5,'interested'),(13,6,'interested'),(13,12,'interested'),
(14,2,'interested'),(14,3,'interested'),(14,7,'interested'),(14,11,'interested'),
(15,1,'interested'),(15,5,'interested'),(15,8,'interested'),(15,12,'interested'),
(16,1,'interested'),(16,4,'interested'),(16,9,'interested'),(16,10,'interested'),(16,11,'interested');

-- ── SEED: TEAM STATS ──────────────────────────────────────
INSERT IGNORE INTO Team_Stats (event_id, main_team, sub_team, contribution, members_involved, logged_by) VALUES
(2, 'communications','design',      'Event promo graphics and poster suite for networking night',      3, 1),
(3, 'communications','design',      'Hackathon branding package and digital asset library',             2, 1),
(5, 'communications','design',      'End-of-semester social banner and Instagram story templates',      2, 1),
(7, 'communications','design',      'React workshop slide deck and promotional material',               2, 1),
(12,'communications','design',      'Banquet invitations, table cards, and digital menu design',        3, 1),
(2, 'communications','socialmedia', 'Instagram and LinkedIn announcement posts for networking night',   2, 1),
(5, 'communications','socialmedia', 'Stories, countdowns, and post-event recap thread',                3, 1),
(8, 'communications','socialmedia', 'AI/ML panel speaker spotlights and countdown stories',            2, 1),
(10,'communications','socialmedia', 'Games night hype reel and signup link campaign',                  2, 1),
(12,'communications','socialmedia', 'Banquet announcement, ticket sales posts, and live coverage',     3, 1),
(1, 'studentsupport','academic',    'Organized study materials and algorithm practice sheets',          4, 2),
(6, 'studentsupport','academic',    'Resume template preparation and review rubric',                    2, 2),
(11,'studentsupport','academic',    'Compiled mock interview questions for Amazon, Google, Meta',       3, 2),
(4, 'studentsupport','academic',    'Python resource guide and dataset curation for workshop',          2, 2),
(6, 'studentsupport','mentorship',  'Paired mentors with attendees for 1-on-1 resume review',          3, 2),
(11,'studentsupport','mentorship',  'Coached 12 students through behavioural interview prep',           4, 2),
(1, 'studentsupport','mentorship',  'Led algorithm problem breakdowns for struggling students',         2, 2),
(2, 'events',        NULL,          'Venue booking, room setup, and day-of logistics for networking',   5, 3),
(5, 'events',        NULL,          'Catering coordination and post-event cleanup for social',          6, 3),
(3, 'events',        NULL,          'Hackathon floor plan, check-in desk, and meals coordination',      4, 3),
(10,'events',        NULL,          'AV setup, gaming station config, and prize table setup',           3, 3),
(12,'events',        NULL,          'Banquet venue liaison, seating arrangement, and AV setup',         5, 3),
(3, 'outreach',      NULL,          'Partner company coordination and sponsor acquisition',             2, 4),
(2, 'outreach',      NULL,          'Speaker scheduling and LinkedIn outreach campaign',                3, 4),
(8, 'outreach',      NULL,          'Alumni speaker recruitment for AI/ML panel',                      2, 4),
(11,'outreach',      NULL,          'Corporate partnerships for intern bootcamp mock interviewers',     3, 4),
(3, 'webtech',       NULL,          'Registration form, live RSVP counter, and team matcher app',       4, 5),
(2, 'webtech',       NULL,          'Events page update, navbar fix, and performance audit',            2, 5),
(7, 'webtech',       NULL,          'Workshop landing page and live code preview environment',          3, 5),
(9, 'webtech',       NULL,          'Git workshop materials and interactive terminal simulator',        2, 5),
(12,'webtech',       NULL,          'Banquet RSVP portal and digital program booklet',                 3, 5);

INSERT IGNORE INTO Organization_Info (org_id, name, description, email, social_links) VALUES
(1,
 'McMaster Computer Science Society',
 'A student-led academic society at McMaster University striving to organize events that help the CS community network and grow.',
 'css@mcmaster.ca',
 '{"instagram":"@macCSS","linkedin":"mac-css","discord":"discord.gg/macCSS"}');