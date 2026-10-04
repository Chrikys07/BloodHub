<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{Auth,Csrf,Permission};
use BloodHub\Services\ChatService;
use RuntimeException;

final class ChatController
{
    public static function index(): void
    {
        self::requirePermission('chat.view',false);
        $pageTitle='HubChat';$pageSubtitle='Comunicação interna';$userAuth=Auth::user();$flash=[];$csrf=Csrf::token();
        require dirname(__DIR__).'/Views/chat/index.php';
    }

    public static function conversations(): void { self::handle(fn(ChatService $s,int $u)=>['conversations'=>$s->conversations($u)]); }
    public static function users(): void { self::requirePermission('chat.view');self::requirePermission('chat.private');self::handle(fn(ChatService $s,int $u)=>['users'=>$s->activeUsers($u)],false); }
    public static function sectors(): void { self::requireChatGroupPermissions();self::handle(fn(ChatService $s,int $u)=>['sectors'=>$s->activeSectors()],false); }
    public static function participants(): void
    {
        self::handle(function(ChatService $s,int $u):array{
            $id=filter_input(INPUT_GET,'conversation_id',FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Conversa invalida.');
            return ['participants'=>$s->participants((int)$id,$u)];
        });
    }
    public static function unread(): void { self::handle(fn(ChatService $s,int $u)=>['unread_count'=>$s->unreadCount($u)]); }
    public static function messages(): void
    {
        self::handle(function(ChatService $s,int $u):array{
            $id=filter_input(INPUT_GET,'conversation_id',FILTER_VALIDATE_INT);$before=filter_input(INPUT_GET,'before_id',FILTER_VALIDATE_INT);
            if(!$id)throw new RuntimeException('Conversa invalida.');
            return ['messages'=>$s->messages((int)$id,$u,$before?(int)$before:null)];
        });
    }
    public static function send(): void
    {
        self::requirePermission('chat.view');self::requirePermission('chat.send');self::requireCsrf();
        self::handle(function(ChatService $s,int $u):array{
            $id=filter_var($_POST['conversation_id']??null,FILTER_VALIDATE_INT);
            if(!$id)throw new RuntimeException('Conversa invalida.');
            return ['message'=>$s->send((int)$id,$u,(string)($_POST['message']??''))];
        },false);
    }
    public static function read(): void
    {
        self::requirePermission('chat.view');self::requireCsrf();self::handle(function(ChatService $s,int $u):array{
            $id=filter_var($_POST['conversation_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Conversa invalida.');
            $s->markRead((int)$id,$u);return [];
        },false);
    }
    public static function privateConversation(): void
    {
        self::requirePermission('chat.view');self::requirePermission('chat.private');self::requireCsrf();self::handle(function(ChatService $s,int $u):array{
            $other=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);if(!$other)throw new RuntimeException('Usuario invalido.');
            return ['conversation_id'=>$s->privateConversation($u,(int)$other)];
        },false);
    }
    public static function groupConversation(): void
    {
        self::requireChatGroupPermissions();self::requireCsrf();self::handle(function(ChatService $s,int $u):array{
            $unit=filter_var($_POST['unit_id']??null,FILTER_VALIDATE_INT);if(!$unit)throw new RuntimeException('Setor invalido.');
            return ['conversation_id'=>$s->unitConversation($u,(int)$unit)];
        },false);
    }
    public static function archiveConversation(): void
    {
        self::requirePermission('chat.view');self::requireCsrf();self::handle(function(ChatService $s,int $u):array{
            $id=filter_var($_POST['conversation_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Conversa invalida.');
            $s->archivePrivate((int)$id,$u);return [];
        },false);
    }

    private static function requireChatGroupPermissions(): void
    {
        self::requirePermission('chat.view');self::requirePermission('chat.send');self::requirePermission('chat.group');
    }

    private static function handle(callable $callback,bool $checkView=true): void
    {
        if($checkView)self::requirePermission('chat.view');
        try{self::json(['ok'=>true]+$callback(new ChatService(),(int)Auth::user()['id']));}
        catch(RuntimeException $e){self::json(['ok'=>false,'message'=>$e->getMessage()],422);}
        catch(\Throwable $e){error_log('[BloodHub Chat] '.$e->getMessage());self::json(['ok'=>false,'message'=>'Nao foi possivel concluir a operacao. Tente novamente.'],500);}
    }
    private static function requirePermission(string $key,bool $json=true): void
    {
        if(!Auth::check()){if($json)self::json(['ok'=>false,'message'=>'Sessao expirada.'],401);header('Location: /login');exit;}
        if(!Permission::can($key)){if($json)self::json(['ok'=>false,'message'=>'Acesso negado.'],403);http_response_code(403);echo 'Acesso negado.';exit;}
    }
    private static function requireCsrf(): void
    {
        if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){
            self::json(['ok'=>false,'message'=>'Nao foi possivel validar a seguranca da solicitacao. Atualize a pagina e tente novamente.'],419);
        }
    }
    private static function json(array $data,int $status=200): never { http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
}
