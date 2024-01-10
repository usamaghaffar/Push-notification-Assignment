<?php


namespace App\Controllers;


use App\Models\PushNotification;
use PDO;
use PDOException;

class PushNotificationController extends Controller
{

    private PDO $pdo;
    private $usersLimit; // 100000K

    public function __construct()
    {
        $dsn = 'mysql:dbname=push;host=localhost';
        $this->pdo = new PDO($dsn, env("DB_USER_NAME"), env("DB_PASSWORD"));
        $this->usersLimit = env('PUSH_TO_N_DEVICES_BY_CRONE');
    }



    /**
     * @api {post} / Request to send
     *
     * @apiVersion 0.1.0
     * @apiName send
     * @apiDescription This method saves the push notification and put it to the queue.
     * @apiGroup Sending
     *
     * @apiBody {string="send"} action API method
     * @apiBody {string} title Title of push notification
     * @apiBody {string} message Message of push notification
     * @apiBody {int} country_id Country ID
     *
     * @apiParamExample {json} Request-Example:
    {"action":"send","title":"Hello","message":"World","country_id":4}
     *
     * @apiSuccessExample {json} Success:
    {"success":true,"result":{"notification_id":123}}
     *
     * @apiErrorExample {json} Failed:
    {"success":false,"result":null}
     */
    public function sendByCountryId(string $title, string $message, int $countryId): ?array
    {
        try {
            
            // First we will prepare our statement and bind Params
            // then fetch results from our query
            $stmt = $this->pdo->prepare("SELECT d.token, d.id as device_id
            FROM users u
            JOIN devices d ON u.id = d.user_id
            WHERE u.country_id = :countryId AND d.expired = 0");

            $stmt->bindParam(':countryId', $countryId, PDO::PARAM_INT);
            $stmt->execute();

            $deviceTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);


            try {

                // We will create only one notification
                // Save push notification to the database
                $saveNotificationStmt = $this->pdo->prepare(
                    "INSERT INTO push_notifications (title, message) VALUES (:title, :message)"
                );
                $saveNotificationStmt->bindParam(':title', $title, PDO::PARAM_STR);
                $saveNotificationStmt->bindParam(':message', $message, PDO::PARAM_STR);
                $saveNotificationStmt->execute();

                // get the notification 
                $notification_id = $this->pdo->lastInsertId();

                // Prepare the statement for insertion
                $saveNotificationStmt = $this->pdo->prepare("
                INSERT INTO notification_devices (notification_id, device_id) VALUES (:notification_id, :device_id)
            ");

                // now we will save device related data in notification_devices 
                // that's how we can reduce our query response and we don't need to apply join
                $this->pdo->beginTransaction();


                // Split deviceTokens into chunks (e.g., chunk size of 100)
                $chunkSize = env('CHUNK_SIZE');
                $deviceTokenChunks = array_chunk($deviceTokens, $chunkSize);

                // Iterate through chunks and execute the insert statements
                foreach ($deviceTokenChunks as $chunk) {
                    foreach ($chunk as $token) {
                        $saveNotificationStmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
                        $saveNotificationStmt->bindParam(':device_id', $token['device_id'], PDO::PARAM_INT);
                        $saveNotificationStmt->execute();
                    }
                }

                // If successfull commit the transaction
                $this->pdo->commit();


                // and then return the response with the transaction id
                if ($notification_id) {
                    return response(true, [
                        'notification_id' => $notification_id
                    ]);
                } else {
                    return response(false, null);
                }
            } catch (PDOException $pdoException) {
                $this->pdo->rollBack();
                return response(false, $pdoException->getMessage());
            }
        } catch (\Exception $e) {
            return response(false, $e->getMessage());
        }
    }

    /**
     * @api {post} / Get details
     *
     * @apiVersion 0.1.0
     * @apiName details
     * @apiDescription This method returns all details by notification ID.
     * @apiGroup Information
     *
     * @apiBody {string="details"} action API method
     * @apiBody {int} notification_id Notification ID
     *
     * @apiParamExample {json} Request-Example:
    {"action":"details","notification_id":123}
     *
     * @apiSuccessExample {json} Success:
    {"success":true,"result":{"id":123,"title":"Hello","message":"World","sent":90000,"failed":10000,"in_progress":100000,"in_queue":123456}}
     *
     * @apiErrorExample {json} Notification not found:
    {"success":false,"result":null}
     */
    public function details(int $notificationID): ?array
    {
        try {
            // get the record from related push_notifications and devices tables
            $stmt = $this->pdo->prepare("SELECT n.title, n.message, n.id as notificationId, 
            SUM(CASE WHEN d.status = 0 THEN 1 ELSE 0 END) as in_queue,
            SUM(CASE WHEN d.status = 1 THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN d.status = 2 THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN d.status = 3 THEN 1 ELSE 0 END) as failed
            FROM push_notifications n
            JOIN notification_devices d ON n.id = d.notification_id
            WHERE n.id = :notificationId
            GROUP BY d.status");

            /* bind $notificationID with :notificationId */
            $stmt->bindParam(':notificationId', $notificationID, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return response(true, $result);
            } else {
                return response(false, null);
            }
        } catch (\Exception $e) {
            return response(false, $e->getMessage());
        }
    }

    /**
     * @api {post} / Sending by CRON
     *
     * @apiVersion 0.1.0
     * @apiName cron
     * @apiDescription This method sends the push notifications from queue.
     * @apiGroup Sending
     *
     * @apiBody {string="cron"} action API method
     *
     * @apiParamExample {json} Request-Example:
    {"action":"cron"}
     *
     * @apiSuccessExample {json} Success and sent:
    {"success":true,"result":[{"notification_id":123,"title":"Hello","message":"World","sent":50000,"failed":10000},{"notification_id":124,"title":"New","message":"World","sent":20000,"failed":20000}]}
     *
     * @apiSuccessExample {json} Success, no notifications in the queue:
    {"success":true,"result":[]}
     */
    
     
    
    /**
     * Method cron
     *
     * @return array
     */
    public function cron(): array
    {
        try {

            $notifications = $this->getQueuedNotifications($this->usersLimit);
            $result = [];
            
            foreach ($notifications as $key => $notification) {

                $sentNotifications = 0;
                $failedNotifications = 0;

                $notification_id = $notification['notification_id'];
                $title = $notification['title'];
                $message = $notification['message'];
                $token = $notification['token'];

                if( PushNotification::send($title, $message, $token)){
                    $sentNotifications++;
                }else{
                    $failedNotifications++;
                }

                // Check if we are looking at the same notification or not
                // if no then initialize counts and title, message, notification_id as well
                if (!isset($result[$notification_id])) {
                    $result[$notification_id] = [
                        'notification_id' => $notification_id,
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'sent' => 0,
                        'failed' => 0,
                    ];
                }

                  // total counts for each notification_id
                  $result[$notification_id]['sent'] += $sentNotifications;
                  $result[$notification_id]['failed'] += $failedNotifications;

            }

            // get array values for the response
            $aggregatedResult = array_values($result);


            return response(true, $aggregatedResult);
        } catch (PDOException $pdoException) {
            return response(false, $pdoException->getMessage());
        }
    }

    
    /**
     * Method getQueuedNotifications
     *
     * @param $limit USERS_LIMIT
     *
     * @return array
     */
    private function getQueuedNotifications($limit)
    {
        try {
            $notifications = [];
            $dataOffset = 0;
            while (true) {

                // Fetch push_notifications in chunks using LIMIT and OFFSET
                $stmt = $this->pdo->prepare("SELECT nd.notification_id as notification_id, n.title, n.message, d.token as token
                        FROM notification_devices nd
                        JOIN push_notifications n ON n.id = nd.notification_id
                        JOIN devices d ON d.id = nd.device_id
                        WHERE nd.status = :status
                        ORDER BY created_at ASC
                        LIMIT :limit OFFSET :offset
                    ");

                $status = 0;

                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $dataOffset, PDO::PARAM_INT);
                $stmt->bindParam(":status", $status, PDO::PARAM_INT);
                $stmt->execute();

                $chunk = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // empty then exit the loop
                if (empty($chunk)) {
                    break;
                }

                // Add the chunk to the notifications array
                $notifications = array_merge($notifications, $chunk);

                // next chunk
                $dataOffset += $limit;
            }

            return $notifications;
        } catch (PDOException $pdoException) {
            return response(false, $pdoException->getMessage());
        }
    }
}
