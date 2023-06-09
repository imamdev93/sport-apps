<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventUser;
use App\Models\Group;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Str;

class BotTelegramController extends Controller
{

    public function setWebhook()
    {
        Telegram::setWebhook(['url' => config('telegram.bots.mybot.webhook_url')]);
    }

    public function removeWebhook()
    {
        Telegram::removeWebhook();
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

        // $this->sendMessage($chatId, $this->formatText('%s', $webhook->message));

        DB::beginTransaction();
        try {
            $userInfo = $webhook->message->from;
            $user = User::where('username', $userInfo->username)->orWhere('chat_id', $userInfo->username)->first();

            if (!$user) {
                $user = User::create(['chat_id' => $userInfo->id,
                    'name' => $userInfo->username,
                    'username' => $userInfo->username,
                    'password' => bcrypt($userInfo->username)
                ]);
            } else {
                $updateUser = $user->update(['chat_id' => $userInfo->id, 'username' => $userInfo->username]);
            }

            $group = $this->getGroup($chatId);
            if (!$group) {
                $create = Group::create([
                    'chat_id' => $chatId,
                    'name' => $webhook->message->chat->title ?? $user->username,
                    'created_by' => $user->id
                ]);
                $user->group()->attach($create->id, ['is_admin' => true]);
                $group = $create;
            }

            if (count($user->group->where('chat_id', $group->chat_id)) == 0) {
                $user->group()->attach($group->id);
            }

            $this->executeCommandBot($chatId, $commands, $message, $user, $group, $replyId);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', $e->getMessage())), $replyId);
        }
    }


    public function executeCommandBot($chatId, $commands, $message, $user, $group, $replyId)
    {

        $success = '\u2705';
        $failed = '\u274c';

        $command = explode('@', $commands[0]);

        switch ($command[0]) {
            case '/start':
                $this->sendMessage($group->chat_id, 'Selamat Datang di IF Sport Bot. klik /help untuk melihat command akuu.', $replyId);
                break;
            case '/help':
                $commands = [
                    'setadmin' => 'Menambahkan user jadi admin di grup bot',
                    'removeadmin' => 'Menghapus user jadi admin di grup bot',
                    'tambahuser' => 'Mmenambahkan user ke grup bot',
                    'listuser' => 'Melihat daftar user terdaftar di grup bot',
                    'ubahnama' => 'Merubah nama user',
                    'event' => 'Melihat event yang sedang aktif',
                    'tambahevent' => 'Menambahkan event',
                    'lastevent' => 'Melihat event terakhir',
                    'join' => 'Bergabung ke event',
                    'unjoin' => 'Batal bergabung ke event',
                    'bayar' => 'Melakukan ceklis setelah membayar iuran.',
                    'reload' => 'Jika Ada Kendala, pakai aku yaa!'
                ];

                if (!empty($getCommand[1])) {
                    $response = $this->formatText('%s' . PHP_EOL, '<b>Cara menggunakan command : </b>');
                    switch (trim($getCommand[1])) {
                        case 'tambahevent':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/tambahevent | (Nama Event) | (lokasi) | (Tanggal ex: Y-m-d) | (Jam ex: 19.00 - 20:00) </b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'tambahuser':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/tambahuser @user1 @user2 @user3 (dilakukan oleh admin yg didaftarkan pada bot)</b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'join':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/join @user1 @user2 @user3</b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'unjoin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/unjoin @user1 @user2 @user3</b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'bayar':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/bayar (jumlah uang) @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'setadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/setadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'ubahnama':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/ubahnama | nama (ex: Imam Fahmi) </b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        case 'removeadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/removeadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response, $replyId);
                            break;
                        default:
                            $this->sendMessage($group->chat_id, '<b>help command tidak ditemukan </b>', $replyId);
                            break;
                    }
                } else {
                    $responses = '';
                    foreach ($commands as $key => $value) {
                        $responses .= $this->formatText('/%s - %s' . PHP_EOL, $key, $value);
                    }
                    $this->sendMessage($group->chat_id, $responses, $replyId);
                }
                break;
            case '/listuser':
                $responses = $this->formatText('<b> %s </b>' . PHP_EOL, 'Daftar User : ');
                foreach ($group->user as $value) {
                    $responses .=  $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, ' ' . $value->name . " (@$value->username) "));
                }
                $this->sendMessage($group->chat_id, $responses, $replyId);
                break;
            case '/event':
                $event = $this->getEventActive($group);
                if(!$event){
                    $this->sendMessage($group->chat_id, '<b> Belum ada event. klik /tambahevent untuk menambahkan event baru </b>', $replyId);
                    break;
                }
                $responses = $this->formatText('%s' . PHP_EOL, '<b>Event Aktif :</b>');
                $responses .= $this->formatText('%s' . PHP_EOL, '');
                $responses .= $this->formatText('%s' . PHP_EOL, "<b>Event : $event->title</b>");
                $responses .= $this->formatText('%s' . PHP_EOL, "<b>Lokasi : $event->location</b>");
                $responses .= $this->formatText('%s' . PHP_EOL, "<b>Tanggal : $event->date</b>");
                $responses .= $this->formatText('%s' . PHP_EOL, "<b>Jam : $event->time WIB </b>");
                $responses .= $this->formatText('%s' . PHP_EOL, '');
                $responses .= $this->formatText('%s' . PHP_EOL, "klik /join gaskeun.");
                $this->sendMessage($group->chat_id, $responses, $replyId);
                break;
            case '/lastevent':
                $event = $this->getLastEvent($group);
                if (!$event) {
                    $this->sendMessage($group->chat_id, '<b> Belum ada event. klik /tambahevent untuk menambahkan event baru </b>', $replyId);
                    break;
                }
                $responses = $this->getEventParticipants($event, false);
                $this->sendMessage($group->chat_id, $responses, $replyId);
                break;
            // case '/tambahgrup':
            //     try {
            //         $exist = $this->getGroup($group->chat_id);
            //         if ($exist) {
            //             $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, ' Grup sudah terdaftar'));
            //         } elseif ($text->message->chat->type == 'group') {
            //             $group = Group::create([
            //                 'chat_id' => $text->getChat()->getId(),
            //                 'name' => $text->message->chat->title,
            //                 'created_by' => $user->id
            //             ]);
            //             $user->group()->attach($group->id, ['is_admin' => true]);
            //             $this->sendMessage($group->chat_id, $this->unicodeToUtf8($success, ' Grup berhasil dibuat'));
            //         } else {
            //             $this->sendMessage($group->chat_id, $this->unicodeToUtf8($failed, ' Mohon maaf tidak dapat menambahkan grup, silahkan untuk membuat membuat grup terlebih dahulu kemudian lakukan command /tambahgrup'));
            //         }
            //     } catch (Exception $e) {
            //         $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
            //     }
            //     break;
            case '/tambahuser':
                try {
                    $replyMessage = explode('@', $message);
                    $responses = '';
                    $this->authorization($group->chat_id, $user, $replyId);
                    $responses .= $this->formatText('<b> %s </b>' . PHP_EOL, 'Hasil : ');
                    if (count($replyMessage) == 1) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan user (ex : /tambahuser @user1)', $replyId);
                    } else {
                        unset($replyMessage[0]);
                        foreach ($replyMessage as $value) {
                            $userCheck = User::where('username', $value)->first();
                            if (!$userCheck) {
                                $newUser = User::create([
                                    'name' => ucfirst(($value)),
                                    'username' => trim($value),
                                    'password' => bcrypt($value),
                                ]);
                                $newUser->group()->attach($group->id);
                                $result = "@$newUser->username Berhasil";
                            } elseif (!$userCheck->group->where('chat_id', $chatId)->first()) {
                                $userCheck->group()->attach($group->id);
                                $result = "@$userCheck->username Berhasil";
                            }else{
                                $result = "@$userCheck->username Sudah Terdaftar";
                            }
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, $result));
                        }

                        $this->sendMessage($chatId, $responses, $replyId);
                    }
                } catch (Exception $e) {
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/tambahevent':
                DB::beginTransaction();
                try {
                    $replyMessage = explode('|', $message);
                    $this->authorization($chatId, $user, $replyId);

                    // $group = $this->getGroup($chatId);
                    if(empty($replyMessage[1]) || empty($replyMessage[2]) || empty($replyMessage[3]) || empty($replyMessage[4])){
                        $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, '<b> Format Salah. /tambahevent | (Nama Event) | (lokasi) | (Tanggal ex: Y-m-d) | (Jam ex: 19.00 - 20:00) </b>')), $replyId);
                        break;
                    }
                    $title = $replyMessage[1];
                    $location = $replyMessage[2];
                    $date = trim($replyMessage[3]);
                    $time = $replyMessage[4];

                    $event = Event::create([
                        'group_id' => $group->id,
                        'title' => $title,
                        'location' => $location,
                        'date' => $date,
                        'time' => $time,
                        'created_by' => $user->id,
                    ]);

                    $responses = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollback();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/join':
                $replyMessage = explode(' ', $message);
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($group);
                    $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', $message);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia. klik /tambahevent untuk menambahkan event baru', $replyId);
                        break;
                    }

                    if(trim($participants[0]) == '/join'){
                        unset($participants[0]);
                    }

                    foreach ($participants as $participant) {
                        $eventUser = User::where('username', $participant)->first();

                        if (!$eventUser) {
                            $user = User::create([
                                'name' => $participant,
                                'username' => $participant,
                                'password' => bcrypt($participant)
                            ]);
                            // $group = $this->getGroup($group->chat_id);
                            $user->group()->attach($group->id, ['is_admin' => false]);
                            $eventUser = $user;
                        }

                        $exist = $this->getEventUserExistIsPay($event->id, $eventUser?->id);
                        if (!$exist) {
                            EventUser::create([
                                'user_id' => $eventUser->id,
                                'event_id' => $event->id,
                                'created_by' => $user->id,
                            ]);
                        }
                    }

                    $responses = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/unjoin':
                $replyMessage = explode(' ', $message);
                $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', $message);
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($group);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia klik /tambahevent untuk menambahkan event baru', $replyId);
                        break;
                    }

                    if (trim($participants[0]) == '/unjoin') {
                        unset($participants[0]);
                    }

                    foreach ($participants as $participant) {
                        $userUnjoin = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExistIsPay($event->id, $userUnjoin->id, false);
                        if ($exist) {
                            $exist->delete();
                        }
                    }
                    $responses = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/bayar':
                $replyMessage = explode(' ', $message);
                DB::beginTransaction();
                try {
                    $this->authorization($group->chat_id, $user, $replyId);
                    $event = $this->getEventActive($group);
                    $amount = $replyMessage[1] ?? 20000;
                    $participants = empty($replyMessage[2]) ? [$user->username] : explode('@', $message);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia klik /tambahevent untuk menambahkan event baru', $replyId);
                        break;
                    }

                    foreach ($participants as $participant) {
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExistIsPay($event->id, $eventUser?->id, false);
                        if (!empty($participant) && $exist) {
                            $exist->update(['amount' => $amount, 'is_pay' => true]);
                        }
                    }

                    $responses = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/setadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $message);
                    $this->authorization($group->chat_id, $user, $replyId);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan username yang akan dijadikan admin ( ex: /setadmin @user)', $replyId);
                        break;
                    }

                    $responses = $this->formatText('%s' . PHP_EOL, '<b>Hasil :</b>');
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        if(!$cekUser){
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "$user tidak ditemukan"));
                            break;
                        }

                        $userGroup = $cekUser->group->where('chat_id', $group->chat_id)->first();
                        if ($userGroup) {
                            $userGroup->pivot->update(['is_admin' => true]);
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($group->chat_id, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/removeadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $message);
                    $this->authorization($group->chat_id, $user, $replyId);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan username yang akan dihapus menjadi admin ( ex: /removeadmin @user)', $replyId);
                        break;
                    }

                    $responses = '';
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        $userGroup = $cekUser->group->where('chat_id', $group->chat_id)->first();
                        if ($cekUser && $userGroup) {
                            $userGroup->pivot->update(['is_admin' => false]);
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $responses .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($group->chat_id, $responses, $replyId);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/ubahnama':
                try {
                    $replyMessage = explode('|', $message);
                    if(empty($replyMessage[1])){
                        $this->sendMessage($group->chat_id, ' Silahkan masukan nama (ex : /ubahnama | Lorem Ipsum)', $replyId);
                        break;
                    }

                    $user->update(['name' => trim($replyMessage[1])]);
                    $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($success, ' ubah nama berhasil'), $replyId);
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())), $replyId);
                }
                break;
            case '/reload':
                $this->setWebhook();
                $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($success, ' reload success'), $replyId);
                break;
            default:
                if (strpos($command[0], '/') == false && !empty($command[0])) {
                    $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($failed, 'Command tidak ada.'), $replyId);
                }
                break;
        }
    }

    public function sendMessage($chatId, $message, $fromId)
    {
        $botToken = config('telegram.bots.mybot.token');

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_to_message_id' => $fromId,
            'parse_mode' => 'html',
        ]);
      
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

    public function getGroup($chatId)
    {
        $grup =  Group::where('chat_id', $chatId)->first();
        return $grup;
    }

    public function authorization($chatId, $user, $replyId)
    {
        // $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', ' Ops anda tidak bisa akses')));

        $isAdmin = $user->group->where('chat_id', $chatId)->first()->pivot->is_admin;
        if (!$isAdmin) {
            $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', ' Ops anda tidak bisa akses')), $replyId);
            die();
        }
    }

    public function getParticipants($event_id)
    {
        return EventUser::where('event_id', $event_id)->get();
    }

    public function getEventParticipants($event, $isActive = true)
    {
        $success = '\u2705';
        $responses = $this->formatText('<b>%s</b>' . PHP_EOL, $event->title);
        $responses .= $this->formatText('<b>Lokasi : %s</b>' . PHP_EOL, $event->location);
        $responses .= $this->formatText('<b>Tanggal : %s</b>' . PHP_EOL, Carbon::parse($event->date)->translatedFormat('l d F Y'));
        $responses .= $this->formatText('<b>Jam : %s</b>' . PHP_EOL, $event->time . ' WIB');
        $responses .= $this->formatText('%s' . PHP_EOL, '');
        $participants = $this->getParticipants($event->id);

        if ($isActive) {
            $responses .= $this->formatText('<b>%s : </b>' . PHP_EOL, 'Yuk List Gaes. Klik /join untuk gabung.');
        } else {
            $responses .= $this->formatText('<b>%s : </b>' . PHP_EOL, 'List Peserta : ');
        }
        if (count($participants) > 0) {
            foreach ($participants as $key => $participant) {
                $no = $key + 1;
                $responses .= $this->formatText("<b>$no. %s %s</b>" . PHP_EOL, $participant->user?->name, $this->unicodeToUtf8($participant->is_pay == true ? $success : '', ''));
            }
        } else {
            $responses .= $this->formatText("%s" . PHP_EOL, ' Belum ada peserta');
        }

        $responses .= $this->formatText('%s' . PHP_EOL, '');
        $responses .= $this->formatText('<b>Total Peserta : %s </b>' . PHP_EOL, count($participants));
        $responses .= $this->formatText('<b>Uang Terkumpul : %s </b>' . PHP_EOL, number_format($event->eventUsers->sum('amount'), 0, '.', '.'));

        if ($isActive) {
            $responses .= $this->formatText('%s' . PHP_EOL, '');
            $responses .= $this->formatText('%s' . PHP_EOL, 'klik /unjoin jika tidak jadi ikut.');
        }


        return $responses;
    }

    public function getEventActive($group)
    {
        return Event::where('group_id', $group->id)->orderByDesc('date')->where('is_active', true)->whereDate('date', '>=', now()->format('Y-m-d'))->first();
    }

    public function getLastEvent($group)
    {
        return Event::where('group_id', $group->id)->whereDate('date', '<', now()->format('Y-m-d'))->orderByDesc('date')->first();
    }

    public function getEventUserExistIsPay($even_id, $user_id, $is_pay = true)
    {
        return EventUser::where('user_id', $user_id)->where('event_id', $even_id)->where('is_pay', $is_pay)->first();
    }
}
