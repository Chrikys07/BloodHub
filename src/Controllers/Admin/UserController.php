<?php
namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;

final class UserController
{
    private const PERMISSION = 'admin.users.manage';
    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $users = Database::connection()->query(
            'SELECT u.id, u.name, u.email, u.status, u.created_at, r.name role_name,
                    c.name client_name, un.name unit_name
             FROM users u LEFT JOIN roles r ON r.id=u.role_id
             LEFT JOIN clients c ON c.id=u.client_id
             LEFT JOIN units un ON un.id=u.primary_unit_id
             ORDER BY u.name'
        )->fetchAll();
        self::view('index', ['users' => $users, 'pageTitle' => 'Usuários']);
    }

    public static function create(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        self::form(null, []);
    }

    public static function store(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        self::save(null);
    }

    public static function edit(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $user = $id ? self::find((int)$id) : null;
        if (!$user) { self::notFound(); return; }
        self::form($user, []);
    }

    public static function update(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $user = $id ? self::find((int)$id) : null;
        if (!$user) { self::notFound(); return; }
        self::save($user);
    }

    private static function save(?array $current): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            self::redirect($current ? '/admin/users/edit?id='.$current['id'] : '/admin/users/create');
        }
        $data = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'professional_name' => trim((string)($_POST['professional_name'] ?? '')) ?: null,
            'professional_council' => trim((string)($_POST['professional_council'] ?? '')) ?: null,
            'professional_registration' => trim((string)($_POST['professional_registration'] ?? '')) ?: null,
            'email' => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
            'role_id' => self::nullableId($_POST['role_id'] ?? null),
            'client_id' => self::nullableId($_POST['client_id'] ?? null),
            'primary_unit_id' => self::nullableId($_POST['primary_unit_id'] ?? null),
            'status' => (string)($_POST['status'] ?? ''),
        ];
        $unitIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['unit_ids']??[])))));
        if($data['primary_unit_id']&&!in_array($data['primary_unit_id'],$unitIds,true))$unitIds[]=$data['primary_unit_id'];
        $password = (string)($_POST['password'] ?? '');
        $errors = [];
        if ($data['name'] === '') $errors[] = 'O nome é obrigatório.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
        if (!$data['role_id']) $errors[] = 'Selecione um perfil.';
        if (!in_array($data['status'], ['active','inactive','blocked'], true)) $errors[] = 'Status inválido.';
        if (!$current && strlen($password) < 8) $errors[] = 'A senha deve ter no mínimo 8 caracteres.';
        if ($current && $password !== '' && strlen($password) < 8) $errors[] = 'A nova senha deve ter no mínimo 8 caracteres.';
        $pdo = Database::connection();
        if ($data['role_id'] && !self::exists($pdo, 'roles', $data['role_id'])) $errors[] = 'O perfil selecionado não existe.';
        if ($data['client_id'] && !self::exists($pdo, 'clients', $data['client_id'])) $errors[] = 'O cliente selecionado não existe.';
        if ($data['primary_unit_id'] && !self::exists($pdo, 'units', $data['primary_unit_id'])) $errors[] = 'A unidade selecionada não existe.';
        if($unitIds){$marks=implode(',',array_fill(0,count($unitIds),'?'));$q=$pdo->prepare("SELECT id,client_id FROM units WHERE status='active' AND id IN ($marks)");$q->execute($unitIds);$valid=$q->fetchAll(PDO::FETCH_ASSOC);if(count($valid)!==count($unitIds))$errors[]='Uma ou mais unidades autorizadas são inválidas.';if($data['client_id']&&array_filter($valid,fn($u)=>(int)$u['client_id']!==$data['client_id']))$errors[]='Todas as unidades autorizadas devem pertencer ao cliente selecionado.';}
        $unique = $pdo->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');
        $unique->execute(['email'=>$data['email'], 'id'=>$current['id'] ?? 0]);
        if ($unique->fetchColumn()) $errors[] = 'Já existe um usuário com este e-mail.';
        if ($errors) { self::form(array_merge($current ?? [], $data), $errors); return; }

        if ($current) {
            $sql = 'UPDATE users SET name=:name,professional_name=:professional_name,professional_council=:professional_council,professional_registration=:professional_registration,email=:email,role_id=:role_id,client_id=:client_id,
                    primary_unit_id=:primary_unit_id,status=:status';
            if ($password !== '') { $sql .= ',password_hash=:password_hash'; $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
            $sql .= ' WHERE id=:id'; $data['id'] = $current['id'];
            $pdo->prepare($sql)->execute($data);
            self::syncUnits($pdo,(int)$current['id'],$unitIds);
            Auth::registerAudit('user.update', 'users', (int)$current['id']);
            Flash::set('success', 'Usuário atualizado com sucesso.');
        } else {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (name,professional_name,professional_council,professional_registration,email,role_id,client_id,primary_unit_id,status,password_hash)
                           VALUES (:name,:professional_name,:professional_council,:professional_registration,:email,:role_id,:client_id,:primary_unit_id,:status,:password_hash)')->execute($data);
            $id = (int)$pdo->lastInsertId();
            self::syncUnits($pdo,$id,$unitIds);
            Auth::registerAudit('user.create', 'users', $id);
            Flash::set('success', 'Usuário criado com sucesso.');
        }
        self::redirect('/admin/users');
    }

    private static function form(?array $user, array $errors): void
    {
        $pdo = Database::connection();
        $roles = $pdo->query('SELECT id,name FROM roles ORDER BY name')->fetchAll();
        $clients = $pdo->query("SELECT id,name FROM clients WHERE status='active' ORDER BY name")->fetchAll();
        $units = $pdo->query("SELECT id,name FROM units WHERE status='active' ORDER BY name")->fetchAll();
        $selectedUnitIds=[];if(!empty($user['id'])){$q=$pdo->prepare('SELECT unit_id FROM user_units WHERE user_id=:id');$q->execute(['id'=>$user['id']]);$selectedUnitIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}elseif(isset($_POST['unit_ids']))$selectedUnitIds=array_map('intval',(array)$_POST['unit_ids']);
        self::view('form', compact('user','errors','roles','clients','units','selectedUnitIds') + ['pageTitle'=>$user ? 'Editar Usuário' : 'Novo Usuário']);
    }

    private static function find(int $id): ?array { $s=Database::connection()->prepare('SELECT * FROM users WHERE id=:id'); $s->execute(['id'=>$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private static function nullableId(mixed $value): ?int { return filter_var($value, FILTER_VALIDATE_INT) ?: null; }
    private static function exists(PDO $pdo, string $table, int $id): bool { $s=$pdo->prepare("SELECT 1 FROM {$table} WHERE id=:id"); $s->execute(['id'=>$id]); return (bool)$s->fetchColumn(); }
    private static function syncUnits(PDO $pdo,int$userId,array$unitIds):void{$pdo->prepare('DELETE FROM user_units WHERE user_id=:user')->execute(['user'=>$userId]);$q=$pdo->prepare('INSERT INTO user_units(user_id,unit_id) VALUES(:user,:unit)');foreach($unitIds as$unit)$q->execute(['user'=>$userId,'unit'=>(int)$unit]);}
    private static function view(string $name, array $vars): void { extract($vars); $flash=Flash::pull(); $csrf=Csrf::token(); $userAuth=Auth::user(); require dirname(__DIR__,2).'/Views/admin/users/'.$name.'.php'; }
    private static function redirect(string $url): never { header('Location: '.$url); exit; }
    private static function notFound(): void { http_response_code(404); echo 'Usuário não encontrado.'; }
}
