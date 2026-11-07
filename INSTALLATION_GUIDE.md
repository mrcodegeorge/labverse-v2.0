# LABVERSE v2.0 — Non-Developer Server Installation Guide

This guide explains how anyone—even without programming or software development experience—can install and launch **LABVERSE v2.0 Enterprise LIMS** on a web hosting server (cPanel / Shared Hosting), a Cloud VPS, or a local clinic computer.

LABVERSE includes a **Built-In Visual Setup Wizard (`/install`)** that automatically checks your server, creates your database, sets up your Laboratory Name & Administrator account, and configures Email (SMTP) & SMS alerts right from your web browser.

---

## Option A: Installing on cPanel / Shared Web Hosting (No Coding Required)

Use this option if you bought standard web hosting (e.g., Namecheap, Hostinger, Bluehost, GoDaddy, or a local hosting provider with cPanel).

### Step 1: Upload the Files
1. Log in to your hosting **cPanel** and click **File Manager**.
2. Open the **`public_html`** folder (or your subdomain folder, e.g., `lims.yourlaboratory.com`).
3. Click **Upload** at the top, select your **LABVERSE `.zip`** package, and wait for it to reach 100%.
4. Right-click the uploaded `.zip` file and click **Extract** into `public_html`.
   *(Note: LABVERSE includes a root `.htaccess` file that automatically routes visitors to the `public/` folder, so you do not need to change any complicated server paths.)*

### Step 2: Set PHP Version to 8.2 or 8.3
1. In cPanel, click **Select PHP Version** or **MultiPHP Manager**.
2. Make sure your domain is set to **PHP 8.2** or **PHP 8.3** and click **Apply**.

### Step 3: Open the Visual Setup Wizard in Your Browser
1. Open your web browser and go to:
   **`https://your-domain.com/install`**
2. Follow the **4 simple screens** in the Visual Setup Wizard:
   - **Screen 1 (Server Check)**: Confirm all PHP items show green checkmarks, then click **Next**.
   - **Screen 2 (Database)**:
     - **Easiest Choice**: Keep **"1-Click Embedded SQLite"** selected. You don't need to create any MySQL database or password!
     - **MySQL Choice**: If you prefer MySQL, create a database in *cPanel → MySQL Database Wizard*, select **MySQL / MariaDB Server** on Screen 2, enter the Database Name, Username, and Password, and click **Test Database Connection**.
   - **Screen 3 (Lab & Admin Profile)**:
     - Type your **Official Laboratory Name**, **Branch Name**, **Phone Number**, and **Address** (these appear on your printed medical reports).
     - Enter your **Administrator Email** and **Password**.
     - Choose either **Full Clinical Demo & Catalogue** (includes sample patients/orders for testing) or **Clean Live Production Laboratory** (includes the 16 diagnostic test panels and reference ranges, with zero demo patients).
   - **Screen 4 (SMTP, SMS & Install)**:
     - Optionally enter your SMTP Email or SMS Gateway API Key (or leave on Sandbox Mode to configure later).
     - Click **"Install & Launch LABVERSE Now"**.

In about 5 seconds, your system will be installed and you can sign in!

---

## Option B: Installing on a Local Clinic Windows PC / Office Server

Use this option to run LABVERSE on a receptionist or laboratory computer inside your clinic.

1. Install **XAMPP** (https://www.apachefriends.org) or **Laragon** (https://laragon.org) so your Windows PC has PHP 8.2+.
2. Open the LABVERSE folder on your computer.
3. Double-click **`start-windows.bat`**.
4. A window will open, automatically prepare the database, and launch **`http://127.0.0.1:8000`** in your web browser!
5. To customize your Laboratory Name, Admin Login, or reset the database anytime, visit **`http://127.0.0.1:8000/install`**.

---

## Option C: Installing on a Cloud Server / VPS (Ubuntu / DigitalOcean / AWS Lightsail)

Use this option if you rented a cloud server running **Ubuntu 22.04 or 24.04 LTS**.

1. Connect to your server terminal and run these 3 lines:
   ```bash
   git clone https://github.com/mrcodegeorge/labverse-v2.0.git /var/www/labverse
   cd /var/www/labverse
   sudo bash install-server.sh
   ```
2. The script automatically installs PHP, Composer, Nginx, SQLite/MySQL, and folder permissions.
3. When it finishes, it will display your setup link:
   **`http://YOUR_SERVER_IP/install`**
4. Open that link in your browser and complete the 4-step Visual Setup Wizard.

---

## Configuring SMTP Email & SMS Gateways After Installation

You can configure or test your Email and SMS gateways at any time without touching any server files:

1. Log in to LABVERSE as **Platform Administrator**.
2. Click **Settings** in the bottom-left sidebar.
3. Use the top tabs:
   - **SMTP Email Integration**:
     - Switch **Mail Delivery Mode** from *Sandbox* to **Live External SMTP Relay Server**.
     - Enter your SMTP Host (e.g., `smtp.gmail.com` or `mail.yourdomain.com`), Port (`587`), Username, Password, and Sender Email.
     - Click **Save SMTP Settings**, then use the **Verify & Test SMTP Delivery** card on the right to send a live test email!
   - **SMS Gateway Integration**:
     - Choose your SMS provider:
       - **Arkesel SMS** (Ghana / West Africa)
       - **mNotify BMS**
       - **Hubtel SMS**
       - **Termii SMS**
       - **Twilio Programmable SMS**
       - **Custom REST HTTP Webhook**
     - Enter your **Sender ID** (up to 11 characters, e.g. `LABVERSE`) and your provider **API Key**.
     - Click **Save SMS Gateway Settings**, then use the **Send Test SMS Message** card on the right to test delivery to your phone!
   - **Automated Triggers & Templates**:
     - Toggle which events automatically send SMS and/or Email (**Patient Welcome**, **Order Placed**, **Official Report Released**, **Critical/Panic Result Alert**, **Payment Receipt**) and customize the message templates.
   - **Communication Outbox**:
     - View every SMS and Email sent by the system, check its delivery status (`SENT`, `SIMULATED`, or `FAILED`), and click **Resend** on any message.

