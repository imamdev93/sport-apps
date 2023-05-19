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
        $webhook =  Telegram::commandsHandler(true);

        $command = $webhook->getChat();
        $getText = $webhook->message?->text;
        $chatId = $command->getId();
        $username = $webhook->message->from->username;
        $getCommand = explode(' ', $getText);
        $failed = '\u274c';

        $user = User::where('username', $username)->first();

        if (!$user) {
            $user = User::create([
                'name' => $username,
                'username' => $username,
                'password' => bcrypt($username)
            ]);
        }
        // $this->sendMessage($chatId, $this->formatText('%s', $webhook->message->from->username));

        $group = $this->getGroup($chatId, $webhook->message->chat->title);
        if (!$group) {
            $create = Group::create([
                'chat_id' => $chatId,
                'name' => $webhook->message->chat->title ?? $user->username,
                'created_by' => $user->id
            ]);
            $user->group()->attach($create->id, ['is_admin' => true]);
            $group = $create;
        }
        // elseif ($webhook->message->chat->type != 'group') {
        //     $this->sendMessage($chatId, ' Bot hanya dapat digunakan di grup');
        //     die();
        // }

        if (count($user->group->where('chat_id', $group->chat_id)) == 0) {
            $user->group()->attach($group->id);
        }

        $this->authenticate($chatId, $getCommand, $webhook, $user, $group);
    }

    public function authenticate($chatId, $getCommand, $text, $user, $group)
    {
        $success = '\u2705';
        $failed = '\u274c';
        $command = explode('@', $getCommand[0]);
        switch ($command[0]) {
            case '/start':
                $this->sendMessage($group->chat_id, 'Selamat Datang di IF Sport Bot. klik /help untuk melihat command akuu.');
                break;
            case '/help':
                $commands = [
                    'setadmin' => 'Menambahkan user jadi admin di grup bot',
                    'removeadmin' => 'Menghapus user jadi admin di grup bot',
                    'tambahuser' => 'Mmenambahkan user ke grup bot',
                    // 'hapususer' => 'Command untuk menghapus user dari grup',
                    'listuser' => 'Melihat daftar user terdaftar di grup bot',
                    'ubahnama' => 'Merubah nama user',
                    'event' => 'Melihat event yang sedang aktif',
                    'tambahevent' => 'Menambahkan event',
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
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'tambahuser':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/tambahuser @user1 @user2 @user3 (dilakukan oleh admin yg didaftarkan pada bot)</b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        // case 'hapususer':
                        //     $response .= $this->formatText('%s' . PHP_EOL, '<b>/hapususer @user1 @user2 @user3 (dilakukan oleh admin grup)</b>');
                        //     $this->sendMessage($group->chat_id, $response);
                        //     break;
                        case 'join':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/join @user1 @user2 @user3</b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'unjoin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/unjoin @user1 @user2 @user3</b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'bayar':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/bayar (jumlah uang) @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'setadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/setadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'ubahnama':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/ubahnama | nama (ex: Imam Fahmi) </b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        case 'removeadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/removeadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($group->chat_id, $response);
                            break;
                        default:
                            $this->sendMessage($group->chat_id, '<b>help command tidak ditemukan </b>');
                            break;
                    }
                } else {
                    $messages = '';
                    foreach ($commands as $key => $value) {
                        $messages .= $this->formatText('/%s - %s' . PHP_EOL, $key, $value);
                    }
                    $this->sendMessage($group->chat_id, $messages);
                }
                break;
            case '/listuser':
                $messages = $this->formatText('<b> %s </b>' . PHP_EOL, 'Daftar User : ');
                foreach ($group->user as $value) {
                    $messages .=  $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, ' ' . $value->name . " (@$value->username) "));
                }
                $this->sendMessage($group->chat_id, $messages);
                break;
            case '/event':
                $event = $this->getEventActive($group);
                if(!$event){
                    $this->sendMessage($group->chat_id, '<b> Belum ada event. klik /tambahevent untuk menambahkan event baru </b>');
                    break;
                }
                $messages = $this->formatText('%s'.PHP_EOL, '<b>Event Aktif :</b>');
                $messages .= $this->formatText('%s'.PHP_EOL, '');
                $messages .= $this->formatText('%s'.PHP_EOL, "<b>Event : $event->title</b>");
                $messages .= $this->formatText('%s'.PHP_EOL, "<b>Lokasi : $event->location</b>");
                $messages .= $this->formatText('%s'.PHP_EOL, "<b>Tanggal : $event->date</b>");
                $messages .= $this->formatText('%s'.PHP_EOL, "<b>Jam : $event->time WIB </b>");
                $messages .= $this->formatText('%s' . PHP_EOL, '');
                $messages .= $this->formatText('%s' . PHP_EOL, "klik /join gaskeun.");
                $this->sendMessage($group->chat_id, $messages);
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
                    $replyMessage = explode('@', $text->message?->text);
                    $messages = '';
                    $this->authorization($group->chat_id, $user);
                    $messages .= $this->formatText('<b> %s </b>' . PHP_EOL, 'Hasil : ');
                    if (count($replyMessage) == 1) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan user (ex : /tambahuser @user1)');
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
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, $result));
                        }

                        $this->sendMessage($chatId, $messages);
                    }
                } catch (Exception $e) {
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/tambahevent':
                DB::beginTransaction();
                try {
                    $replyMessage = explode('|', $text->message?->text);
                    $this->authorization($chatId, $user);

                    // $group = $this->getGroup($chatId);
                    if(empty($replyMessage[1]) || empty($replyMessage[2]) || empty($replyMessage[3]) || empty($replyMessage[4])){
                        $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, '<b> Format Salah. /tambahevent | (Nama Event) | (lokasi) | (Tanggal ex: Y-m-d) | (Jam ex: 19.00 - 20:00) </b>')));
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

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollback();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            // case '/hapususer':
            //     try {
            //         $replyMessage = explode('@', $text->message?->text);
            //         $this->authorization($group->chat_id, $user);
            //         $messages = $this->formatText('<b> %s </b>' . PHP_EOL, 'Hasil : ');
            //         if (count($replyMessage) == 1) {
            //             $this->sendMessage($group->chat_id, ' Silahkan masukan user (ex : /hapususer @user1)');
            //         } else {
            //             unset($replyMessage[0]);
            //             foreach ($replyMessage as $value) {
            //                 $userCheck = User::where('username', $value)->first();
            //                 $group = $this->getGroup($group->chat_id);
            //                 if (!$userCheck) {
            //                     $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, " user tidak ditemukan"));
            //                 } else {
            //                     $userCheck->group()->detach($group->id);
            //                     $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, " @$value berhasil"));
            //                 }
            //             }

            //             $this->sendMessage($group->chat_id, $messages);
            //         }
            //     } catch (Exception $e) {
            //         $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
            //     }
            //     break;
            case '/join':
                $replyMessage = explode(' ', $text->message?->text);
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($group);
                    $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', $text->message?->text);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia. klik /tambahevent untuk menambahkan event baru');
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

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/unjoin':
                $replyMessage = explode(' ', $text->message?->text);
                $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', $text->message?->text);
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($group);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia klik /tambahevent untuk menambahkan event baru');
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
                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/bayar':
                $replyMessage = explode(' ', $text->message?->text);
                DB::beginTransaction();
                try {
                    $this->authorization($group->chat_id, $user);
                    $event = $this->getEventActive($group);
                    $amount = $replyMessage[1] ?? 20000;
                    $participants = empty($replyMessage[2]) ? [$user->username] : explode('@', $text->message?->text);
                    if (!$event) {
                        $this->sendMessage($group->chat_id, ' Belum ada event tersedia klik /tambahevent untuk menambahkan event baru');
                        break;
                    }

                    foreach ($participants as $participant) {
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExistIsPay($event->id, $eventUser?->id, false);
                        if (!empty($participant) && $exist) {
                            $exist->update(['amount' => $amount, 'is_pay' => true]);
                        }
                    }

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($group->chat_id, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/setadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $text->message?->text);
                    $this->authorization($group->chat_id, $user);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan username yang akan dijadikan admin ( ex: /setadmin @user)');
                        break;
                    }

                    $messages = $this->formatText('%s'.PHP_EOL,'<b>Hasil :</b>');
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        if(!$cekUser){
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "$user tidak ditemukan"));
                            break;
                        }

                        $userGroup = $cekUser->group->where('chat_id', $group->chat_id)->first();
                        if ($userGroup) {
                            $userGroup->pivot->update(['is_admin' => true]);
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($group->chat_id, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/removeadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $text->message?->text);
                    $this->authorization($group->chat_id, $user);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($group->chat_id, ' Silahkan masukan username yang akan dihapus menjadi admin ( ex: /removeadmin @user)');
                        break;
                    }

                    $messages = '';
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        $userGroup = $cekUser->group->where('chat_id', $group->chat_id)->first();
                        if ($cekUser && $userGroup) {
                            $userGroup->pivot->update(['is_admin' => false]);
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($group->chat_id, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/ubahnama':
                try {
                    $replyMessage = explode('|', $text->message?->text);
                    if(empty($replyMessage[1])){
                        $this->sendMessage($group->chat_id, ' Silahkan masukan nama (ex : /ubahnama | Lorem Ipsum)');
                        break;
                    }

                    $user->update(['name' => trim($replyMessage[1])]);
                    $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($success, ' ubah nama berhasil'));
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($group->chat_id, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/reload':
                $this->setWebhook();
                $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($success, ' reload success'));
                break;
            default:
                if (strpos($getCommand[0],'/') == false && !empty($getCommand[0])) {
                    $this->sendMessage($group->chat_id,  $this->unicodeToUtf8($failed, 'Command tidak ada.'));
                }
                break;
        }
    }

    public function sendMessage($chatId, $message)
    {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'html'
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

    public function getGroup($chatId, $name)
    {
        $grup =  Group::where('chat_id', $chatId)->first();
        return $grup;
    }

    public function authorization($chatId, $user)
    {
        // $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', ' Ops anda tidak bisa akses')));

        $isAdmin = $user->group->where('chat_id', $chatId)->first()->pivot->is_admin;
        if (!$isAdmin) {
            $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c', ' Ops anda tidak bisa akses')));
            die();
        }
    }

    public function getParticipants($event_id)
    {
        return EventUser::where('event_id', $event_id)->get();
    }

    public function getEventParticipants($event)
    {
        $success = '\u2705';
        $messages = $this->formatText('<b>%s</b>' . PHP_EOL, $event->title);
        $messages .= $this->formatText('<b>Lokasi : %s</b>' . PHP_EOL, $event->location);
        $messages .= $this->formatText('<b>Tanggal : %s</b>' . PHP_EOL, Carbon::parse($event->date)->translatedFormat('l d F Y'));
        $messages .= $this->formatText('<b>Jam : %s</b>' . PHP_EOL, $event->time . ' WIB');
        $messages .= $this->formatText('%s' . PHP_EOL, '');
        $participants = $this->getParticipants($event->id);

        $messages .= $this->formatText('<b>%s : </b>' . PHP_EOL, 'Yuk List Gaes. Klik /join untuk gabung.');
        if (count($participants) > 0) {
            foreach ($participants as $key => $participant) {
                $no = $key + 1;
                $messages .= $this->formatText("<b>$no. %s %s</b>" . PHP_EOL, $participant->user?->name, $this->unicodeToUtf8($participant->is_pay == true ? $success : '', ''));
            }
        } else {
            $messages .= $this->formatText("%s" . PHP_EOL, ' Belum ada peserta');
        }

        $messages .= $this->formatText('%s' . PHP_EOL, '');
        $messages .= $this->formatText('<b>Total Peserta : %s </b>' . PHP_EOL, count($participants));
        $messages .= $this->formatText('<b>Uang Terkumpul : %s </b>' . PHP_EOL, number_format($event->eventUsers->sum('amount'), 0, '.', '.'));
        $messages .= $this->formatText('%s' . PHP_EOL, '');
        $messages .= $this->formatText('%s' . PHP_EOL, 'klik /unjoin jika tidak jadi ikut.');

        return $messages;
    }

    public function getEventActive($group)
    {
        return Event::where('group_id', $group->id)->orderByDesc('date')->where('is_active', true)->whereDate('date', '>=', now()->format('Y-m-d'))->first();
    }

    public function getEvent($event_name, $group)
    {
        return Event::where('group_id', $group->id)->where('title', trim($event_name))->where('is_active', true)->first();
    }

    public function getEventUserExistIsPay($even_id, $user_id, $is_pay = true)
    {
        return EventUser::where('user_id', $user_id)->where('event_id', $even_id)->where('is_pay', $is_pay)->first();
    }
}
