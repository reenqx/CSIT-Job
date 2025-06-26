# CSIT Job Finder & Skill Development Platform

<p align="center">
  <img src="./logo.png" alt="Naresuan University CSIT Logo" width="300">
</p>

A web platform designed to connect students and professors within the Computer Science and Information Technology (CSIT) department of Naresuan University. This project facilitates the posting of part-time jobs, internships, and project opportunities, allowing students to gain practical experience and professors to find capable assistants.

## About The Project

This platform serves as a dedicated job board for the CSIT department. It addresses the need for a centralized system where professors can post available opportunities and students can easily find and apply for them. The application also includes features for skill tracking, student reviews, and notifications to create a dynamic and interactive ecosystem for professional development.

The system is designed to serve four key user roles: Students, Professors, an Admin, and an Executive.

## Key Features

-   **User Roles & Permissions**: A robust system that caters to the unique needs of four different actors:
    -   **Students**: Can manage their profiles, showcase skills and hobbies, browse for jobs, submit applications, and view their review history.
    -   **Professors**: Can manage their profiles, post new job opportunities, manage existing posts (edit, open/close), view and manage applications, and review students upon project completion.
    -   **Admins**: Can access an admin panel to manage users and site content.
    -   **Executives**: Can view a high-level dashboard with key metrics and analytics about platform engagement.
-   **Job Management**: Professors can create detailed job postings with required skills, categories, rewards (hourly, monetary), and timeframes.
-   **Application System**: Students can submit their GPA, resume, and other details through a simple application form. Professors receive notifications and can approve or reject applicants.
-   **Review & Rating System**: After a job is completed, professors can provide reviews and ratings for students across multiple categories, which are then displayed on the student's profile.
-   **Dynamic Filtering & Search**: Users can filter jobs by category and sort them. Application lists can also be filtered by student major and year.
-   **Notification System**: Real-time notifications for job applications, approvals, rejections, and job reports.

## Technology Stack

This project is built with a classic web technology stack, focusing on server-side rendering and vanilla JavaScript.

-   **Backend**: PHP 
-   **Database**: MySQL
-   **Frontend**: HTML, CSS, Vanilla JavaScript (with AJAX for dynamic content)
-   **Server**: Designed to run on a standard LAMP/WAMP/MAMP stack (Apache, MySQL, PHP).

## Prerequisites & Installation

To run this project locally, you will need a local server environment with PHP and MySQL.

-   [XAMPP](https://www.apachefriends.org/index.html) (for Windows, macOS, Linux)
-   or [WAMP](https://www.wampserver.com/en/) (for Windows)
-   or [MAMP](https://www.mamp.info/en/mamp/) (for macOS)

### Setup Instructions

1.  **Clone the repository** to your local machine inside your server's web directory (e.g., `C:/xampp/htdocs/`).
    ```sh
    git clone [your-repository-url] csit-job-finder
    ```

2.  **Set up the database**:
    -   Open your database management tool (like phpMyAdmin).
    -   Create a new database named `ip`.
    -   Import the `ip.sql` file in sample-database branch (you should export your current database structure into this file) into the `ip` database.

3.  **Configure the database connection**:
    -   Open the  `database.php` file in your root.
    -   Update the `$servername`, `$username`, `$password`, and `$dbname` variables to match your local environment's credentials.

4.  **Start your local server**:
    -   Launch your XAMPP/WAMP/MAMP control panel and start the Apache and MySQL services.

5.  **Access the application**:
    -   Open your web browser and navigate to `http://localhost/csit-job-finder/` (or the name you gave the folder).
    -   The homepage should load.

## Usage

You can log in with the following sample credentials to test the different user roles.

-   **Student Role**:
    -   **ID**: `65312121`
    -   **Password**: `stu1234`
-   **Professor Role**:
    -   **ID**: `CSIT0131`
    -   **Password**: `teach1234`
-   **Admin Role**:
    -   **ID**: `admin0141`
    -   **Password**: `admin1234`
-   **Executive Role**:
    -   **ID**: `exec0146`
    -   **Password**: `exec1234`

*Note: You will need to ensure these users exist in your database.*
