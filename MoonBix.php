<?php

class MoonBix {
    private $token;
    private $headers;
    private $game_response;
    private $curr_time;
    private $rs;
    private $log;
    private $accessToken;

    public function __construct($token) {
        $this->token = $token;
        $this->curr_time = round(microtime(true) * 1000);

        $this->headers = [
            'authority: www.binance.com',
            'accept: */*',
            'accept-language: en-US,en;q=0.9,ar-US;q=0.8,ar;q=0.7,en-GB;q=0.6,en-US;q=0.5',
            'clienttype: web',
            'content-type: application/json',
            'lang: en',
            'origin: https://www.binance.com',
            'referer: https://www.binance.com/en/game/tg/moon-bix',
            'sec-ch-ua: "Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
        ];
    }

    private function request($url, $data = [], $method = 'POST') {
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
        ];

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    public function login() {
        $data = [
            "queryString" => $this->token,
            "socialType" => "telegram"
        ];

        $response = $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/third-party/access/accessToken', $data);

        if ($response && isset($response['data']['accessToken'])) {
            $this->accessToken = $response['data']['accessToken'];
            $this->headers[] = 'x-growth-token: ' . $this->accessToken;
            return true;
        }

        return false;
    }

    public function user_info() {
        $data = ['resourceId' => 2056];
        return $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/mini-app-activity/third-party/user/user-info', $data);
    }

    public function get_list_tasks() {
        $data = ['resourceId' => 2056];
        return $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/mini-app-activity/third-party/task/list', $data);
    }

    public function complete_task($resourceId) {
        $data = [
            "resourceIdList" => [$resourceId],
            "refferalCode" => ""
        ];

        $response = $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/mini-app-activity/third-party/task/complete', $data);

        return isset($response['success']) ? $response['success'] : false;
    }

    public function start_game() {
        $data = ['resourceId' => 2056];
        $response = $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/mini-app-activity/third-party/game/start', $data);

        $this->game_response = $response;

        if ($response && $response['code'] == '000000') {
            return true;
        }

        if (isset($response['code']) && $response['code'] == '116002') {
            echo "Attempts not enough!\n";
        } else {
            echo "ERROR!\n";
        }

        return false;
    }

    private function random_data_type($type, $end_time, $item_size, $item_pts) {
        // Convert end time to integer
        $end_time = (int)$end_time;
        // Calculate the pick time based on current time and a random sleep duration
        $pick_time = $this->curr_time + $this->rs;
        
        // Ensure pick time does not exceed end time
        if ($pick_time >= $end_time) {
            $pick_time = $end_time - 1000; // Avoid picking in the last 1000 milliseconds
        }
    
        // Randomly generate hook positions and shot angle
        $hook_pos_x = round(mt_rand(75, 275) + mt_rand() / mt_getrandmax(), 3);
        $hook_pos_y = round(mt_rand(199, 251) + mt_rand() / mt_getrandmax(), 3);
        $hook_shot_angle = round(mt_rand(-1, 1) + mt_rand() / mt_getrandmax(), 3);
        $hook_hit_x = round(mt_rand(100, 400) + mt_rand() / mt_getrandmax(), 3);
        $hook_hit_y = round(mt_rand(250, 700) + mt_rand() / mt_getrandmax(), 3);
    
        // Determine item type and point values based on the type parameter
        if ($type == 1) { // Coin
            $item_type = 1;
            $item_s = $item_size; // Size of the item
            $point = mt_rand(20, 300); // Increase the range of random points for coins
        } elseif ($type == 2) { // Bonus
            $item_type = 2;
            $item_s = $item_size; // Size of the bonus item
            $point = $item_size + $item_pts; // Points for bonus
        } elseif ($type == 0) { // No item
            $item_type = 0;
            $item_s = $item_size; // Size is still passed in
            $point = mt_rand(1, 200); // Random point for no item
        } else { // Default case
            // Set to default values
            $hook_hit_x = 0;
            $hook_hit_y = 0;
            $item_type = mt_rand(0, 2); // Random item type (either coin or bonus)
            $item_s = mt_rand(1, 100); // Random size
            $point = mt_rand(1, 200); // Random point
        }
    
        // Return the data as a string formatted with '|' delimiter
        return "{$pick_time}|{$hook_pos_x}|{$hook_pos_y}|{$hook_shot_angle}|{$hook_hit_x}|{$hook_hit_y}|{$item_type}|{$item_s}|{$point}";
    }
    
    private function encrypt($text, $key) {
        // Generate a random initialization vector (IV)
        $iv = openssl_random_pseudo_bytes(12);
        $iv_base64 = base64_encode($iv); // Encode IV to Base64 for storage
        $iv16 = substr($iv_base64, 0, 16); // Use first 16 bytes of IV
        // Encrypt the text using AES-256-CBC
        $cipher = openssl_encrypt($text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv16);
        $ciphertext_base64 = base64_encode($cipher); // Encode ciphertext to Base64
        // Return concatenated IV and ciphertext
        return $iv_base64 . $ciphertext_base64;
    }
    
    public function gameData() {
        try {
            $timer = 45; // Game duration
            $end_time = (int)((microtime(true) + $timer) * 1000); // Calculate end time
            
            // Increase random pick time range to allow more picks
            $random_pick_time = mt_rand(6, 12); // New range for picking items (6 to 12)
            $total_obj = 0; // Total number of objects available
            $key_for_game = $this->game_response['data']['gameTag']; // Key for encryption
    
            // Initialize object types
            $obj_type = [
                "coin" => [],
                "bonus" => "" // Trap has been removed as per request
            ];
    
            // Process the item settings from the game response
            foreach ($this->game_response['data']['cryptoMinerConfig']['itemSettingList'] as $obj) {
                $total_obj += $obj['quantity']; // Update total number of objects
                if ($obj['type'] == "BONUS") {
                    $obj_type['bonus'] = "{$obj['rewardValueList'][0]},{$obj['size']}"; // Set bonus info
                }
                foreach ($obj['rewardValueList'] as $reward) {
                    if ((int)$reward > 0) { // Only consider positive rewards (coins)
                        $obj_type['coin'][$reward] = "{$obj['size']},{$obj['quantity']}"; // Store coin details
                    }
                }
            }
    
            // Adjust the limit to ensure more coins picked
            $limit = min($total_obj, $random_pick_time); // Limit can be based on total objects and random pick time
            $random_pick_sth_times = mt_rand(5, $limit); // Randomly choose how many items to pick (at least 5)
    
            $picked_bonus = false; // Flag to track if bonus has been picked
            $picked = 0; // Count of items picked
            $game_data_payload = []; // Store game data
            $score = 0; // Initialize score
    
            // Main loop for picking items until time runs out or items are picked
            while ($end_time > $this->curr_time && $picked < $random_pick_sth_times) {
                $this->rs = mt_rand(1500, 2500); // Random sleep duration
                $random_reward = mt_rand(1, 100); // Randomly decide reward type
                
                // Adjusting probabilities for coins (90% chance)
                if ($random_reward <= 90 && count($obj_type['coin']) > 0) {
                    $picked++;
                    $reward_d = array_rand($obj_type['coin']); // Randomly select a coin
                    $details = explode(',', $obj_type['coin'][$reward_d]); // Get coin details
                    $quantity = $details[1]; // Quantity of this coin
                    $item_size = $details[0]; // Size of the coin
    
                    if ((int)$quantity > 0) { // If there's a coin to pick
                        $score += (int)$reward_d; // Increase score
                        $game_data_payload[] = $this->random_data_type(1, $end_time, $item_size, 0); // Log the coin data
                        if ((int)$quantity - 1 > 0) { // Decrement quantity
                            $obj_type['coin'][$reward_d] = "$item_size," . ((int)$quantity - 1);
                        } else {
                            unset($obj_type['coin'][$reward_d]); // Remove if quantity is zero
                        }
                    }
                // Adjusting probabilities for bonuses (70% chance, but only if bonus not picked yet)
                } elseif ($random_reward > 90 && $random_reward <= 100 && !$picked_bonus && !empty($obj_type['bonus'])) {
                    $picked++;
                    $picked_bonus = true; // Bonus has been picked
                    $details = explode(',', $obj_type['bonus']); // Get bonus details
                    $size = $details[1]; // Size of the bonus
                    $pts = $details[0]; // Points for the bonus
                    $score += (int)$pts; // Update score
                    $game_data_payload[] = $this->random_data_type(2, $end_time, (int)$size, (int)$pts); // Log the bonus data
                } else {
                    // Log a no-item event
                    $game_data_payload[] = $this->random_data_type(-1, $end_time, 0, 0);
                }
    
                // Update the current time based on the random sleep duration
                $this->curr_time += $this->rs;
            }
    
            // Prepare the payload data for encryption
            $data_pl = implode(';', $game_data_payload);
            $game_payload = $this->encrypt($data_pl, $key_for_game); // Encrypt the data
    
            // Set the game data with payload, score, and debug info
            $this->game = [
                "payload" => $game_payload,
                "log" => $score,
                "debug" => $data_pl
            ];
    
            return true; // Successfully generated game data
        } catch (Exception $e) {
            // Log any errors that occur during processing
            error_log("Unknown error while trying to get game data: " . $e->getMessage());
            return false; // Return false if an error occurs
        }
    }

    public function complete_game() {

        $data = [
            'resourceId' => 2056,
            'payload' => $this->game['payload'],//fuck payload bullshit
            'log' => $this->game['log'],
        ];

        $response = $this->request('https://www.binance.com/bapi/growth/v1/friendly/growth-paas/mini-app-activity/third-party/game/complete', $data);

        return isset($response['success']) ? $response['success'] : false;
    }

    public function start() {

        if (!$this->login()) {
            echo "Login Failed !!\n";
            return;
        }
        echo "Logged in.\n";

        if (!$this->start_game()) {
            echo "Failed to start game !!\n";
            return;
        }
        echo "Game Started.\n";

        $this->sleep(48, "Waiting game result");

        $this->gameData();

        if (!$this->complete_game()) {
            echo "Failed to complete game !!\n";
            return;
        }

        echo "Game completed, Coins Received: " . $this->game['log'] . "\n";

        $userInfo = $this->user_info();
        if ($userInfo) {
            echo "Balance: " . $userInfo['data']['metaInfo']['totalGrade'] . "\n";
        }
        $tasks = $this->get_list_tasks();
        if ($tasks && $tasks['success']) {
            foreach ($tasks['data']['data'] as $task) {
                foreach ($task['taskList']['data'] as $subTask) {
                    if ($subTask['status'] !== 'COMPLETED' && $subTask['type'] !== 'THIRD_PARTY_BIND') {
                        $resourceId = $subTask['resourceId'];
                        $amount = $subTask['rewardList'][0]['amount'];
                        if ($this->complete_task($resourceId)) {
                            echo "Task completed successfully: Reward: $amount\n";
                        }
                    }
                }
            }
        } else {
            echo "No tasks available\n";
        }
    }

    public function sleep($sec, $dat) {
        for ($i = $sec; $i >= 0; $i--) {
            echo "$dat for $i seconds\r";
            sleep(1);
        }
    }
}

while (true) {
    $tokens = file('query.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($tokens as $token) {
        echo "=============================\n";
        $moon = new MoonBix(trim($token));
        $moon->start();
        echo "=============================\n";
        sleep(5);
    }
    $rand_sleep = rand(450, 750);
    $moon->sleep($rand_sleep, "Sleeping");
}
