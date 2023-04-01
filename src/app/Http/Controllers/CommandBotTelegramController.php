<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CommandBotTelegramController extends Controller
{
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
                    'tambahgrup' => 'Command untuk menambahkan grup ke bot',
                    'tambahuser' => 'Command untuk menambahkan user ke grup',
                    'hapususer' => 'Command untuk menghapus user dari grup',
                    'listuser' => 'Command untuk melihat daftar user di grup',
                    'tambahevent' => 'Command untuk menambahkan event/acara',
                    'join' => 'Command untuk bergabung ke event/acara',
                    'bayar' => 'Command untuk melakukan ceklis setelah membayar iuran.',
                    'reload' => 'Jika Ada Kendala, pakai aku yaa!'
                ];

                if(!empty($getCommand[1])){
                    $response = $this->formatText('%s'.PHP_EOL,'<b>Cara menggunakan command : </b>');
                    switch (trim($getCommand[1])) {
                        case 'tambahgrup':
                            $response .= $this->formatText('%s'.PHP_EOL,'<b>/tambahgrup </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'tambahuser':
                            $response .= $this->formatText('%s'.PHP_EOL,'<b>/tambahuser @user1 @user2 @user3 (dilakukan oleh admin grup)</b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'hapususer':
                            $response .= $this->formatText('%s'.PHP_EOL,'<b>/hapususer @user1 @user2 @user3 (dilakukan oleh admin grup)</b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'tambahevent':
                            $response .= $this->formatText('%s'.PHP_EOL,'<b>/tambahevent | <Nama Event> | <Lokasi> |  <Tanggal> | <Jam> </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'join':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/join | <Nama Event> | @user1 @user2 @user3  </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        case 'bayar':
                            $response .= $this->formatText('%s' . PHP_EOL, '<b>/bayar <jumlah uang> @user1 @user2 @user3 </b>');
                            $this->sendMessage($chatId, $response);
                            break;
                        default:
                            $this->sendMessage($chatId, '<b>help command tidak ditemukan </b>');
                            break;
                        break;
                    }
                }else{
                    $messages = '';
                    foreach ($commands as $key => $value) {
                        $messages .= $this->formatText('/%s - %s' . PHP_EOL, $key, $value);
                    }
                    $this->sendMessage($chatId, $messages);
                }
                break;
            case '/listuser':
                $group = $this->getGroup($chatId);
                $messages = $this->formatText('<b> %s </b>'.PHP_EOL, 'Daftar User : ');
                foreach($group->user as $value){
                    $messages .=  $this->formatText('%s'.PHP_EOL, $this->unicodeToUtf8($success,' '.$value->name." (@$value->username) "));
                }
                $this->sendMessage($chatId, $messages);
                break;
            case '/tambahgrup':
                try{
                    $exist = Group::where('chat_id', $text->getChat()->getId())->first();
                    if($exist){
                        $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, ' Grup sudah terdaftar'));
                    }elseif($text->getMessage()->chat->type == 'group'){
                        $group = Group::create([
                            'chat_id' => $text->getChat()->getId(),
                            'name' => $text->getMessage()->chat->title,
                            'created_by' => $user->id
                        ]);
                        $user->group()->attach($group->id, ['is_admin' => true]);
                        $this->sendMessage($chatId, $this->unicodeToUtf8($success, ' Grup berhasil dibuat'));
                    }else{
                        $this->sendMessage($chatId, $this->unicodeToUtf8($failed, ' Mohon maaf tidak dapat menambahkan grup, silahkan untuk membuat membuat grup terlebih dahulu kemudian lakukan command /tambahgrup'));
                    }
                }catch(Exception $e){
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/tambahuser':
                try{
                    $replyMessage = explode('@', $text->getMessage()->getText());
                    $messages = '';
                    $this->authorization($chatId, $user);
                    $messages .= $this->formatText('<b> %s </b>'.PHP_EOL, 'User Behasil ditambahkan : ');
                    if(count($replyMessage) == 1){
                        $this->sendMessage($chatId, ' Silahkan masukan user (ex : /tambahuser @user1)');
                    }else{
                        foreach($replyMessage as $value){
                            if(trim($value) != '/tambahuser'){
                                $userCheck = User::where('username', $value)->first();
                                $getGroup = Group::where('chat_id', $chatId)->first();
                                if(!$userCheck){
                                    $newUser = User::create([
                                        'name' => ucfirst(($value)),
                                        'username' => trim($value),
                                        'password' => bcrypt($value),
                                    ]);
                                    $newUser->group()->attach($getGroup->id);
                                }else{
                                    $userCheck->group()->where('chat_id', $chatId)->update(['group_id' => $getGroup->id]);
                                }
                                $messages .= $this->formatText('%s'.PHP_EOL, $this->unicodeToUtf8($success, " @$value berhasil"));
                            }
                        }

                        $this->sendMessage($chatId, $messages);
                    }
                }catch(Exception $e){
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/tambahevent':
                DB::beginTransaction();
                try{
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
                }catch(Exception $e){
                    DB::rollback();
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/hapususer':
                try{
                    $replyMessage = explode('@', $text->getMessage()->getText());
                    $this->authorization($chatId, $user);
                    $messages = $this->formatText('<b> %s </b>'.PHP_EOL, 'User Behasil dihapus : ');
                    if(count($replyMessage) == 1){
                        $this->sendMessage($chatId, ' Silahkan masukan user (ex : /hapususer @user1)');
                    }else{
                        foreach($replyMessage as $value){
                            if(trim($value) != '/hapususer'){
                                $userCheck = User::where('username', $value)->first();
                                $group = $this->getGroup($chatId);
                                if(!$userCheck){
                                    $messages .= $this->formatText('%s'.PHP_EOL, $this->unicodeToUtf8($failed, " user tidak ditemukan"));
                                }else{
                                    $userCheck->group()->detach($group->id);
                                    $messages .= $this->formatText('%s'.PHP_EOL, $this->unicodeToUtf8($success, " @$value berhasil"));
                                }

                            }
                        }

                        $this->sendMessage($chatId, $messages);
                    }
                }catch(Exception $e){
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/join':
                $replyMessage = explode('|', $text->getMessage()->getText());
                DB::beginTransaction();
                try{
                    $event = $this->getEventActive($chatId);
                    $participants = empty($replyMessage[1]) ? [$user->username] : explode('@', trim($replyMessage[1]));
                    if(!$event){
                        $this->sendMessage($chatId, ' Belum ada event tersedia');
                        break;
                    }

                    foreach($participants as $participant){
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExist($event->id, $eventUser?->id);
                        if(!empty($participant) && !$exist){
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
                }catch(Exception $e){
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/bayar':
                $replyMessage = explode(' ', $text->getMessage()->getText());
                DB::beginTransaction();
                try{
                    $event = $this->getEventActive($chatId);
                    $amount = $replyMessage[1] ?? 20000;
                    $participants = empty($replyMessage[2]) ? [$user->username] : explode('@', $text->getMessage()->getText());
                    if(!$event){
                        $this->sendMessage($chatId, ' Belum ada event tersedia');
                        break;
                    }

                    foreach($participants as $participant){
                        $eventUser = User::where('username', $participant)->first();
                        $exist = $this->getEventUserExist($event->id, $eventUser?->id);
                        if(!empty($participant) && $exist){
                           $exist->update(['amount' => $amount, 'is_pay' => true]);
                        }
                    }

                    $messages = $this->getEventParticipants($event);

                    $this->sendMessage($chatId, $messages);
                    DB::commit();
                }catch(Exception $e){
                    DB::rollBack();
                    $this->sendMessage($chatId, $this->formatText('%s'.PHP_EOL, $e->getMessage()));
                }
                break;
            case '/reload':
                $this->setWebhook();
                $this->sendMessage($chatId,  $this->unicodeToUtf8($success, ' reload success'));
                break;
            default:
                $this->sendMessage($chatId,  $this->unicodeToUtf8($failed, ' Ups Command tidak ada'));
                break;
        }
    }
}
