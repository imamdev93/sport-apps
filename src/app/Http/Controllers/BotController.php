<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    public function setWebhook()
    {
        try {
            Telegram::setWebhook(['url' => config('telegram.bots.mybot.webhook_url')]);
            Log::info('Setup Webhook Success');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    public function removeWebhook()
    {
        try {
            Telegram::removeWebhook();
            Log::info('remove webhook success');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function getWebhookBot()
    {
        $webhook =  Telegram::getWebhookUpdate();

        $this->authenticateUser($webhook);
    }

    public function authenticateUser($webhook)
    {
        $chatId = $webhook->getChat()->getId();
        $message = $webhook->message?->text;
        $commands = explode(' ', $message);
        $replyId = $webhook->message?->message_id;

        DB::beginTransaction();
        try {
            $this->executeCommandBot($chatId, $commands, $message, $replyId);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', $e->getMessage())), $replyId);
        }
    }


    public function executeCommandBot($chatId, $commands, $message, $replyId)
    {
        $success = '\u2705';
        $failed = '\u274c';

        $command = explode('@', $commands[0]);

        switch ($command[0]) {
            case '/start':
                $this->sendMessage($chatId, 'Selamat Datang di IF Sport Bot. klik /help untuk melihat command akuu.', $replyId);
                break;
            case '/help':
                $commands = [
                    'random' => 'Random Tim Berdasarkan posisi',
                    'reload' => 'Jika Ada Kendala, pakai aku yaa!'
                ];
                $responses = '';
                foreach ($commands as $key => $value) {
                    $responses .= $this->formatText('/%s - %s' . PHP_EOL, $key, $value);
                }
                $this->sendMessage($chatId, $responses, $replyId);
                break;
            case '/random':
                $totalTeam = $this->header($commands)['team'];
                $isFG = $this->header($commands)['fg'];
                $date = Carbon::parse($this->header($commands)['date']);
                $time = $this->header($commands)['time'];
                $pay = $this->header($commands)['pay'];

                $responses = '';
                $members = DB::table(
                    DB::raw(
                        '(SELECT 
                        name,
                        is_member, 
                        position,
                        ROW_NUMBER() OVER (PARTITION BY position ORDER BY RANDOM()) AS row_num
                        FROM users) 
                        AS ranked'
                    )
                )
                    ->where('row_num', '<=', $totalTeam)
                    ->where('is_member', true)
                    ->get();

                $customDate = Carbon::parse($date)->translatedFormat('l, d F Y');
                $weekOfMonth = Carbon::parse($date)->weekOfMonth;
                $monthAndYear = Carbon::parse($date)->translatedFormat('F Y');

                $eventName = "Fun Game JDS FC Bulan " . $monthAndYear;
                if ($weekOfMonth == 1) {
                    $eventName = "Trofeo JDS FC $monthAndYear";
                }

                $responses = $this->formatText('<b>%s</b>' . PHP_EOL, $this->unicodeToUtf8('&#127942;', $eventName));
                $responses .= $this->formatText('<b>%s</b>' . PHP_EOL, $this->unicodeToUtf8('&#127967;', ' BIS Dragons (https://g.co/kgs/4MqwnS) '));
                $responses .= $this->formatText('<b>%s</b>' . PHP_EOL, $this->unicodeToUtf8('&#128467;', ' ' . $customDate));
                $responses .= $this->formatText('<b>%s</b>' . PHP_EOL, $this->unicodeToUtf8('&#9200;', ' ' . $time . ' WIB'));
                $responses .= $this->formatText('<b>%s</b>' . PHP_EOL, $this->unicodeToUtf8('&#128184;', ' Non Member ' . $pay));
                $responses .= $this->formatText('<b>%s</b> ' . PHP_EOL, $this->unicodeToUtf8('&#128179;', ' 108127135337 (Bank Jago a.n Rachadian Novansyah) '));
                if ($isFG) {
                    $responses .= $this->formatText('<b>%s</b> ' . PHP_EOL, $this->unicodeToUtf8('&#128248;', ' Rafa Images (https://instagram.com/rafa.images)  '));
                }
                $responses .= $this->formatText('%s' . PHP_EOL, '');

                $teams = [];
                $alphabet = ['A', 'B', 'C', 'D', 'E'];

                foreach ($members as $value) {
                    $position = $this->mappingPosition($value->position);
                    for ($i = 1; $i <= $totalTeam; $i++) {
                        if ($i == $value->row_num) {
                            $teams[$i][] = $value->name . ' (' . $position . ')';
                        }
                    }
                }

                foreach ($teams as $key => $team) {
                    $teamName = $alphabet[$key - 1] ?? 'F';
                    $no = 1;
                    $responses .= $this->formatText('<b>%s</>' . PHP_EOL, 'TIM ' . $teamName);
                    foreach ($team as $value) {
                        $responses .= $this->formatText('%s' . PHP_EOL, $no++ . '. ' . $value);
                    }
                    $responses .= $this->formatText('%s' . PHP_EOL, '');
                    $no = 0;
                }

                $players = $totalTeam * 7;
                $gk = $totalTeam;

                $responses .= $this->formatText('%s' . PHP_EOL, '');
                $responses .= $this->formatText('<b>%s</b>' . PHP_EOL, 'Notes:');
                $responses .= $this->formatText('%s' . PHP_EOL, '1. JDS dan Alumni JDS (member dan non member)');
                $responses .= $this->formatText('%s' . PHP_EOL, '2. Datang 15 menit sebelum bertanding.');
                $responses .= $this->formatText('%s' . PHP_EOL, '3. Bagi yang muslim bisa sholat magrib bareng di Lapang.');
                $responses .= $this->formatText('%s' . PHP_EOL, "4. Maksimal Pemain " . $players + $gk . " orang ( $gk kiper, $players pemain )");
                $responses .= $this->formatText('%s' . PHP_EOL, '5. Tim dibuat secara acak sesuai posisi.');
                $responses .= $this->formatText('%s' . PHP_EOL, '6. Bagi member yang berhalangan hadir, bisa langsung hapus dari listnya');

                $this->sendMessage($chatId, $responses, $replyId);
                break;
            case '/generate-report':
                $username = $commands[1] ?? null;
                $password = $commands[2] ?? null;

                if (empty($username) || empty($password)) {
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, '/generate-report (username/email) (password)')), $replyId);
                    break;
                }

                $getBody = Http::post("https://n8n.digitalservice.id/webhook/ec14c8d2-2037-4286-a041-8d5616371f53", [
                    'username' => $commands[1],
                    'password' => $commands[2],
                ]);
                $response = json_decode($getBody, true);
                $jsonString = stripslashes($response['message']);
                $emoji = $success;
                $responMessage = $response['message'];

                $userNotFound = stripos($jsonString, 'user not found');
                if ($userNotFound) {
                    $emoji = $failed;
                    $responMessage = 'username ' . $commands[1] . ' tidak ditemukan';
                }

                $wrongPassword = stripos($jsonString, 'wrong password');
                if ($wrongPassword) {
                    $emoji = $failed;
                    $responMessage = 'Password salah';
                }

                $this->sendMessage($chatId, $this->unicodeToUtf8($emoji, $responMessage), $replyId);
                Log::info('Generate Report', [
                    'message' => $responMessage,
                    'data' => $commands[1]
                ]);
                break;
            case '/reload':
                $this->setWebhook();
                $this->sendMessage($chatId, $this->unicodeToUtf8($success, ' reload success'), $replyId);
                break;
            default:
                if (strpos($command[0], '/') !== false && !empty($command[0])) {
                    $this->sendMessage($chatId, $this->unicodeToUtf8($failed, 'Maaf, Command tidak ditemukan. /help untuk melihat command yang bisa digunakan'), $replyId);
                }
                break;
        }
    }

    public function sendMessage($chatId, $message, $fromId)
    {
        $botToken = config('telegram.bots.mybot.token');

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'reply_to_message_id' => $fromId,
                // 'parse_mode' => 'html',
            ]);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }

    public function unicodeToUtf8($string, $message)
    {
        $icon = html_entity_decode(
            preg_replace(
                "/U\+([0-9A-F]{4})/",
                "&#x\\1;",
                $string
            ),
            ENT_NOQUOTES,
            'UTF-8'
        );

        return json_decode(('"' . $icon . $message . '"'));
    }

    public function formatText($format, $message1, $message2 = null)
    {
        return sprintf($format, $message1, $message2);
    }

    private function mappingPosition($position)
    {
        $label = 'KIPER';

        if (in_array($position, [2, 3, 4])) {
            $label = 'BEK';
        } elseif (in_array($position, [5, 6])) {
            $label = 'TENGAH';
        } elseif (in_array($position, [7, 8])) {
            $label = 'DEPAN';
        }

        return $label;
    }

    private function header($commands)
    {
        // Get the current date
        $currentDate = Carbon::now();
        // Get the date for the next Wednesday
        $date = $currentDate->next(Carbon::WEDNESDAY)->translatedFormat('Y-m-d');

        $result = [
            'team' => 5,
            'fg' => false,
            'date' => $date,
            'time' => '18.30-21.30',
            'pay' => '35k'
        ];

        $regex = "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/";

        for ($i = 1; $i <= count($commands); $i++) {
            if (!empty($commands[$i])) {
                if (is_numeric($commands[$i])) {
                    $result['team'] = $commands[$i];
                } elseif (strtoupper($commands[$i] == 'FG')) {
                    $result['fg'] = true;
                } elseif (preg_match($regex, $commands[$i])) {
                    $result['date'] = $commands[$i];
                } elseif (strpos($commands[$i], '.')) {
                    $result['time'] = $commands[$i];
                } elseif (
                    strpos($commands[$i], 'k') ||
                    strpos($commands[$i], 'rb')
                ) {
                    $result['pay'] = $commands[$i];
                }
            }
        }

        return $result;
    }
}
