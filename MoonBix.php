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
        $end_time = (int)$end_time;
        $pick_time = $this->curr_time + $this->rs;
        if ($pick_time >= $end_time) {
            $pick_time = $end_time - 1000;
        }

        $hook_pos_x = round(mt_rand(75, 275) + mt_rand() / mt_getrandmax(), 3);
        $hook_pos_y = round(mt_rand(199, 251) + mt_rand() / mt_getrandmax(), 3);
        $hook_shot_angle = round(mt_rand(-1, 1) + mt_rand() / mt_getrandmax(), 3);
        $hook_hit_x = round(mt_rand(100, 400) + mt_rand() / mt_getrandmax(), 3);
        $hook_hit_y = round(mt_rand(250, 700) + mt_rand() / mt_getrandmax(), 3);

        if ($type == 1) {
            $item_type = 1;
            $item_s = $item_size;
            $point = mt_rand(1, 200);
        } elseif ($type == 2) {
            $item_type = 2;
            $item_s = $item_size;
            $point = $item_size + $item_pts;
        } elseif ($type == 0) {
            $item_type = 0;
            $item_s = $item_size;
            $point = mt_rand(1, 200);
        } else {
            $hook_hit_x = 0;
            $hook_hit_y = 0;
            $item_type = mt_rand(0, 2);
            $item_s = mt_rand(1, 100);
            $point = mt_rand(1, 200);
        }

        return "{$pick_time}|{$hook_pos_x}|{$hook_pos_y}|{$hook_shot_angle}|{$hook_hit_x}|{$hook_hit_y}|{$item_type}|{$item_s}|{$point}";
    }

    private function encrypt($text, $key) {
        $iv = openssl_random_pseudo_bytes(12);
        $iv_base64 = base64_encode($iv);
        $iv16 = substr($iv_base64, 0, 16);
        $cipher = openssl_encrypt($text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv16);
        $ciphertext_base64 = base64_encode($cipher);
        return $iv_base64 . $ciphertext_base64;
    }

    public function gameData() {
        try {
            $timer = 45;
            $end_time = (int)((microtime(true) + 45) * 1000);
            $random_pick_time = mt_rand(2, 10);
            $total_obj = 0;
            $key_for_game = $this->game_response['data']['gameTag'];

            $obj_type = [
                "coin" => [],
                "trap" => [],
                "bonus" => ""
            ];

            foreach ($this->game_response['data']['cryptoMinerConfig']['itemSettingList'] as $obj) {
                $total_obj += $obj['quantity'];
                if ($obj['type'] == "BONUS") {
                    $obj_type['bonus'] = "{$obj['rewardValueList'][0]},{$obj['size']}";
                }
                foreach ($obj['rewardValueList'] as $reward) {
                    if ((int)$reward > 0) {
                        $obj_type['coin'][$reward] = "{$obj['size']},{$obj['quantity']}";
                    } else {
                        $obj_type['trap'][abs((int)$reward)] = "{$obj['size']},{$obj['quantity']}";
                    }
                }
            }

            $limit = min($total_obj, $random_pick_time);
            $random_pick_sth_times = mt_rand(1, $limit);
            $picked_bonus = false;
            $picked = 0;
            $game_data_payload = [];
            $score = 0;

            while ($end_time > $this->curr_time && $picked < $random_pick_sth_times) {
                $this->rs = mt_rand(1500, 2500);
                $random_reward = mt_rand(19, 100);

                if ($random_reward <= 20 && count($obj_type['trap']) > 0) {
                    $picked++;
                    $reward_d = array_rand($obj_type['trap']);
                    $details = explode(',', $obj_type['trap'][$reward_d]);
                    $quantity = $details[1];
                    $item_size = $details[0];

                    if ((int)$quantity > 0) {
                        $score = max(0, $score - (int)$reward_d);
                        $game_data_payload[] = $this->random_data_type(0, $end_time, $item_size, 0);
                        if ((int)$quantity - 1 > 0) {
                            $obj_type['trap'][$reward_d] = "$item_size," . ((int)$quantity - 1);
                        } else {
                            unset($obj_type['trap'][$reward_d]);
                        }
                    }
                } elseif ($random_reward > 20 && $random_reward <= 60 && count($obj_type['coin']) > 0) {
                    $picked++;
                    $reward_d = array_rand($obj_type['coin']);
                    $details = explode(',', $obj_type['coin'][$reward_d]);
                    $quantity = $details[1];
                    $item_size = $details[0];

                    if ((int)$quantity > 0) {
                        $score += (int)$reward_d;
                        $game_data_payload[] = $this->random_data_type(1, $end_time, $item_size, 0);
                        if ((int)$quantity - 1 > 0) {
                            $obj_type['coin'][$reward_d] = "$item_size," . ((int)$quantity - 1);
                        } else {
                            unset($obj_type['coin'][$reward_d]);
                        }
                    }
                } elseif ($random_reward > 60 && $random_reward <= 80 && !$picked_bonus) {
                    $picked++;
                    $picked_bonus = true;
                    $details = explode(',', $obj_type['bonus']);
                    $size = $details[1];
                    $pts = $details[0];
                    $score += (int)$pts;
                    $game_data_payload[] = $this->random_data_type(2, $end_time, (int)$size, (int)$pts);
                } else {
                    $game_data_payload[] = $this->random_data_type(-1, $end_time, 0, 0);
                }

                $this->curr_time += $this->rs;
            }

            $data_pl = implode(';', $game_data_payload);
            $game_payload = $this->encrypt($data_pl, $key_for_game);

            $this->game = [
                "payload" => $game_payload,
                "log" => $score,
                "debug" => $data_pl
            ];

            return true;
        } catch (Exception $e) {
            error_log("Unknown error while trying to get game data: " . $e->getMessage());
            return false;
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

        $this->sleep(42, "Waiting game result");

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
    $moon->sleep(1200, "Sleeping");
}
