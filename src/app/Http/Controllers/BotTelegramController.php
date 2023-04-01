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

    public function getWebhookBot()
    {
        $webhook =  Telegram::commandsHandler(true);

        $command = $webhook->getChat();
        $getText = $webhook->getMessage()->getText();
        $chatId = $command->getId();
        $username = $webhook->getMessage()->from->username;
        $getCommand = explode(' ', $getText);
        $failed = '\u274c';

        $user = User::where('username', $username)->first();

        if (!$user) {
            User::create([
                'name' => $username,
                'username' => $username,
                'password' => bcrypt($username)
            ]);
        }

        $group = $this->getGroup($chatId);
        if (!$group && $webhook->getMessage()->chat->type == 'group') {
            $create = Group::create([
                'chat_id' => $chatId,
                'name' => $webhook->getMessage()->chat->title,
                'created_by' => $user->id
            ]);
            $user->group()->attach($create->id, ['is_admin' => true]);
            $group = $create;
        }elseif(!$webhook->getMessage()->chat->type == 'group'){
            $this->sendMessage($chatId, ' Bot hanya dapat digunakan di grup');
        }

        if (count($user->group) == 0 || !$user->group()->where('chat_id', $chatId)->first()) {
            $user->group()->attach($group->id);
        }

        $this->authenticate($chatId, $getCommand, $webhook, $user);
    }

    public function authenticate($chatId, $getCommand, $text, $user)
    {
        $success = '\u2705';
        $failed = '\u274c';

        switch ($getCommand[0]) {
            case '/start':
                $this->sendMessage($chatId, 'Selamat Datang di IF Sport Bot. klik /help untuk melihat command akuu.');
                break;
            case '/help':
                $commands = [
                    'setadmin' => 'Command untuk menambahkan user jadi admin di grup',
                    'removeadmin' => 'Command untuk menghapus user jadi admin di grup',
                    'tambahuser' => 'Command untuk menambahkan user ke grup',
                    'hapususer' => 'Command untuk menghapus user dari grup',
                    'listuser' => 'Command untuk melihat daftar user terdaftar di grup',
                    'tambahevent' => 'Command untuk menambahkan event/acara',
                    'join' => 'Command untuk bergabung ke event/acara',
                    'unjoin' => 'Command untuk batal bergabung ke event/acara',
                    'bayar' => 'Command untuk melakukan ceklis setelah membayar iuran.',
                    'reload' => 'Jika Ada Kendala, pakai aku yaa!'
                ];

                if (!empty($getCommand[1])) {
                    $response = $this->formatText('%s' . PHP_EOL, '<b>Cara menggunakan command : </b>');
                    switch (trim($getCommand[1])) {
                        case 'tambahuser':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/tambahuser @user1 @user2 @user3 (dilakukan oleh admin grup)</b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'hapususer':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/hapususer @user1 @user2 @user3 (dilakukan oleh admin grup)</b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'tambahevent':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/tambahevent | <Nama Event> | <Lokasi> |  <Tanggal> | <Jam> </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'join':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/join @user1 @user2 @user3 (default event aktif yg terakhir dibuat) </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'unjoin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/unjoin</b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'bayar':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/bayar <jumlah uang> @user1 @user2 @user3 </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'setadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/setadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'removeadmin':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/removeadmin @user1 @user2 @user3 </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        default:
                            $this->sendMessage($chatId, '<b>help command tidak ditemukan </b>');
                            break;
                    }
                } else {
                    $messages = '';
                    foreach ($commands as $key => $value) {
                        $messages .= $this->formatText('/%s - %s' . PHP_EOL, $key, $value);
                    }
                    $this->sendMessage($chatId, $messages);
                }
                break;
            case '/listuser':
                $group = $this->getGroup($chatId);
                $messages = $this->formatText('<b> %s </b>' . PHP_EOL, 'Daftar User : ');
                foreach ($group->user as $value) {
                    $messages .=  $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, ' ' . $value->name . " (@$value->username) "));
                }
                $this->sendMessage($chatId, $messages);
                break;
            // case '/tambahgrup':
            //     try {
            //         $exist = $this->getGroup($chatId);
            //         if ($exist) {
            //             $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, ' Grup sudah terdaftar'));
            //         } elseif ($text->getMessage()->chat->type == 'group') {
            //             $group = Group::create([
            //                 'chat_id' => $text->getChat()->getId(),
            //                 'name' => $text->getMessage()->chat->title,
            //                 'created_by' => $user->id
            //             ]);
            //             $user->group()->attach($group->id, ['is_admin' => true]);
            //             $this->sendMessage($chatId, $this->unicodeToUtf8($success, ' Grup berhasil dibuat'));
            //         } else {
            //             $this->sendMessage($chatId, $this->unicodeToUtf8($failed, ' Mohon maaf tidak dapat menambahkan grup, silahkan untuk membuat membuat grup terlebih dahulu kemudian lakukan command /tambahgrup'));
            //         }
            //     } catch (Exception $e) {
            //         $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
            //     }
            //     break;
            case '/tambahuser':
                try {
                    $replyMessage = explode('@', $text->getMessage()->getText());
                    $messages = '';
                    $this->authorization($chatId, $user);
                    $messages .= $this->formatText('<b> %s </b>' . PHP_EOL, 'User Behasil ditambahkan : ');
                    if (count($replyMessage) == 1) {
                        $this->sendMessage($chatId, ' Silahkan masukan user (ex : /tambahuser @user1)');
                    } else {
                        unset($replyMessage[0]);
                        foreach ($replyMessage as $value) {
                            $userCheck = User::where('username', $value)->first();
                            $getGroup = Group::where('chat_id', $chatId)->first();
                            if (!$userCheck) {
                                $newUser = User::create([
                                    'name' => ucfirst(($value)),
                                    'username' => trim($value),
                                    'password' => bcrypt($value),
                                ]);
                                $newUser->group()->attach($getGroup->id);
                            } else {
                                $userCheck->group()->where('chat_id', $chatId)->update(['group_id' => $getGroup->id]);
                            }
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, " @$value berhasil"));
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
                    $replyMessage = explode('|', $text->getMessage()->getText());
                    $this->authorization($chatId, $user);

                    $group = $this->getGroup($chatId);
                    $title = $replyMessage[1] ?? 'Fun Futsal';
                    $location = $replyMessage[2] ?? 'Pasaga Unpar';
                    $date = $replyMessage[3] ?? Carbon::now()->addDays(7)->format('Y-m-d');
                    $time = $replyMessage[4] ?? '19';

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
            case '/hapususer':
                try {
                    $replyMessage = explode('@', $text->getMessage()->getText());
                    $this->authorization($chatId, $user);
                    $messages = $this->formatText('<b> %s </b>' . PHP_EOL, 'User Behasil dihapus : ');
                    if (count($replyMessage) == 1) {
                        $this->sendMessage($chatId, ' Silahkan masukan user (ex : /hapususer @user1)');
                    } else {
                        unset($replyMessage[0]);
                        foreach ($replyMessage as $value) {
                            $userCheck = User::where('username', $value)->first();
                            $group = $this->getGroup($chatId);
                            if (!$userCheck) {
                                $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, " user tidak ditemukan"));
                            } else {
                                $userCheck->group()->detach($group->id);
                                $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, " @$value berhasil"));
                            }
                        }

                        $this->sendMessage($chatId, $messages);
                    }
                } catch (Exception $e) {
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/join':
                $replyMessage = explode(' ', $text->getMessage()->getText());
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($chatId);
                    $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', $text->getMessage()->getText());
                    if (!$event) {
                        $this->sendMessage($chatId, ' Belum ada event tersedia');
                        break;
                    }

                    if(trim($participants[0]) == '/join'){
                        unset($participants[0]);
                    }
                    
                    foreach ($participants as $participant) {
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExist($event->id, $eventUser?->id);
                        if (!$exist) {
                            EventUser::create([
                                'user_id' => $eventUser->id,
                                'event_id' => $event->id,
                                'created_by' => $user->id,
                            ]);
                        }
                    }

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/unjoin':
                $replyMessage = explode(' ', $text->getMessage()->getText());
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($chatId);
                    if (!$event) {
                        $this->sendMessage($chatId, ' Belum ada event tersedia');
                        break;
                    }

                    $exist = $this->getEventUserExist($event->id, $user->id);
                    if ($exist) {
                        $exist->delete();
                    }

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/bayar':
                $replyMessage = explode(' ', $text->getMessage()->getText());
                DB::beginTransaction();
                try {
                    $event = $this->getEventActive($chatId);
                    $amount = $replyMessage[1] ?? 20000;
                    $participants = empty($replyMessage[2]) ? [$user->username] : explode('@', $text->getMessage()->getText());
                    if (!$event) {
                        $this->sendMessage($chatId, ' Belum ada event tersedia');
                        break;
                    }

                    foreach ($participants as $participant) {
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExist($event->id, $eventUser?->id);
                        if (!empty($participant) && $exist) {
                            $exist->update(['amount' => $amount, 'is_pay' => true]);
                        }
                    }

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/setadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $text->getMessage()->getText());
                    $this->authorization($chatId, $user);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($chatId, ' Silahkan masukan username yang akan dijadikan admin ( ex: /setadmin @user)');
                        break;
                    }

                    $messages = '';
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        if(!$cekUser){
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "$user tidak ditemukan"));
                            break;
                        }

                        $userGroup = $cekUser->group->where('chat_id', $chatId)->first();
                        if ($userGroup) {
                            $userGroup->pivot->update(['is_admin' => true]);
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/removeadmin':
                DB::beginTransaction();
                try {
                    $replyMessage = explode(' ', $text->getMessage()->getText());
                    $this->authorization($chatId, $user);
                    if (empty($replyMessage[1])) {
                        $this->sendMessage($chatId, ' Silahkan masukan username yang akan dihapus menjadi admin ( ex: /removeadmin @user)');
                        break;
                    }

                    $messages = '';
                    unset($replyMessage[0]);
                    foreach ($replyMessage as $user) {
                        $cekUser = User::where('username', str_replace('@', '', $user))->first();
                        $userGroup = $cekUser->group->where('chat_id', $chatId)->first();
                        if ($cekUser && $userGroup) {
                            $userGroup->pivot->update(['is_admin' => false]);
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($success, "@$cekUser->username Berhasil"));
                        } else {
                            $messages .= $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, "@$cekUser->username Tidak terdaftar digrup ini"));
                        }
                    }

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8($failed, $e->getMessage())));
                }
                break;
            case '/reload':
                $this->setWebhook();
                $this->sendMessage($chatId,  $this->unicodeToUtf8($success, ' reload success'));
                break;
            default:
                if (strpos($getCommand[0],'/') == false) {
                    $this->sendMessage($chatId,  $this->unicodeToUtf8($failed, ' Ups Command tidak ada'));
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

    public function getGroup($chatId)
    {
        return Group::where('chat_id', $chatId)->first();
    }

    public function authorization($chatId, $user)
    {
        $isAdmin = $user->group->where('chat_id', $chatId)->first()->pivot->is_admin;
        if (!$isAdmin) {
            $this->sendMessage($chatId, $this->formatText('%s' . PHP_EOL, $this->unicodeToUtf8('\u274c',' Mohon maaf anda bukan admin di grup ini')));
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

        $messages .= $this->formatText('<b>%s : </b>' . PHP_EOL, 'Yuk List Gaes');
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

        return $messages;
    }

    public function getEventActive($chatId)
    {
        $group = $this->getGroup($chatId);
        return Event::where('group_id', $group->id)->orderByDesc('date')->where('is_active', true)->first();
    }

    public function getEvent($event_name, $chatId)
    {
        $group = $this->getGroup($chatId);
        return Event::where('group_id', $group->id)->where('title', trim($event_name))->where('is_active', true)->first();
    }

    public function getEventUserExist($even_id, $user_id)
    {
        return EventUser::where('user_id', $user_id)->where('event_id', $even_id)->first();
    }
}
