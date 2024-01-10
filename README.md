# Push-notification-Assignment

## 🌟 Key Database implementations:

#### 👉 For database tables used indexes and also applied indexes on foreign keys
#### 👉 For saving push notifications added <b>[push_notification](database/migrations/20240110071018_push_notifications_table.php)</b> migration
#### 👉 To make our system optimized and responsive added another table to keep record of queued notifications migration it is named as [notification_devices](database/migrations/20240110071917_notification_devices.php)
#### 👉 <b>[notification_devices](database/migrations/20240110071917_notification_devices.php)</b> migration have two foreign keys notification_id and device_id, device_id is also our <b>index</b> and it contains status field that is an integer field and it is represented by 0- Queued, 1- In-Progress, 2-Sent, 3- Failed, having integer statuses do not stress our database
#### 👉 Many-to-Many relationship

## 🌟 Key Logic Implementations:

### 🔰 PDO used across <b>[PushNotificationController](app/Controllers/PushNotificationController.php)</b>

#### 1️⃣ Starting with [sendByCountryId()](app/Controllers/PushNotificationController.php):

<ul>
    <li>With the help of PDO used prepared statements to fetch data</li>
    <li>Used join statements to fetch records from related tables</li>
    <li>Applied JOIN on devices table with users to get records only where country_id matches the param: $countryId</li>
    <li>Lastly added an AND condition to get only records where device token is not expired</li>
    <li>For storing the record in notification_devices used DB Transactions</li>
    <li>Processed data in chunks *Note:<b>I added a variable inside env named CHUNK_SIZE</b></li>
</ul>

#### 2️⃣ Implementation of [details($notificationId)](app/Controllers/PushNotificationController.php):

<ul>
    <li>With the help of PDO used prepared statements to fetch data</li>
    <li>Used join statements to fetch records from related tables</li>
    <li>Applied JOIN on notification_devices table with push_notifications to get records only where notification_id matches the param: $notificationId</li>
    <li>Used SQL CASE satement inside COUNT() to get status based count for notification details</li>
</ul>

#### 3️⃣ Implementation of [cron()](app/Controllers/PushNotificationController.php):
#### 🌟 Most important function of the application

<ul>
    <li>For fetching queued notifications I created a custom function named <b>getQueuedNotifications($limit)</b> witha a limit parameter that we defined in env as <b>CHUNK_SIZE</b></li>
    <li>Used JOIN statement on multiple tables i.e. push_notifications with notification_devices to get notification related record i.e. queue status and general information of device notification like if it is queued or not and notification_devices with devices to get the token</li>
    <li>As we are sending notifications to N number of users I used OFFSET and LIMIT to process data in an optimized manner using chunks</li>
    <li>Then I loop through each notification and show the aggreated counts with the general details of the notification i.e. title, message, sent, failed etc.</li>
</ul>