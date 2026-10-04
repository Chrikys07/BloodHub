<?php
declare(strict_types=1);

namespace BloodHub\Controllers\Admin;

use BloodHub\Core\AdminGuard;
use BloodHub\Core\Auth;
use BloodHub\Core\Csrf;
use BloodHub\Core\Database;
use BloodHub\Core\Flash;
use PDO;

final class ClientController
{
    private const PERMISSION = 'admin.clients.manage';

    public static function index(): void
    {
        AdminGuard::enforce(self::PERMISSION);
        $clients = Database::connection()->query('SELECT * FROM clients ORDER BY name')->fetchAll();
        self::view('index', ['clients' => $clients, 'pageTitle' => 'Clientes']);
    }

    public static function create(): void { AdminGuard::enforce(self::PERMISSION); self::form(null, []); }
    public static function store(): void { AdminGuard::enforce(self::PERMISSION); self::save(null); }
    public static function edit(): void { AdminGuard::enforce(self::PERMISSION); $client=self::requested(); if(!$client){self::notFound();return;} self::form($client,[]); }
    public static function update(): void { AdminGuard::enforce(self::PERMISSION); $client=self::requested(); if(!$client){self::notFound();return;} self::save($client); }

    private static function save(?array $current): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            self::redirect($current ? '/admin/clients/edit?id='.(int)$current['id'] : '/admin/clients/create');
        }
        $data = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'client_type' => (string)($_POST['client_type'] ?? ''),
            'document' => trim((string)($_POST['document'] ?? '')) ?: null,
            'address'=>trim((string)($_POST['address']??''))?:null,
            'district'=>trim((string)($_POST['district']??''))?:null,
            'city'=>trim((string)($_POST['city']??''))?:null,
            'state'=>strtoupper(trim((string)($_POST['state']??'')))?:null,
            'postal_code'=>trim((string)($_POST['postal_code']??''))?:null,
            'contact_name'=>trim((string)($_POST['contact_name']??''))?:null,
            'phone_extension'=>trim((string)($_POST['phone_extension']??''))?:null,
            'email' => mb_strtolower(trim((string)($_POST['email'] ?? ''))) ?: null,
            'phone' => trim((string)($_POST['phone'] ?? '')) ?: null,
            'status' => (string)($_POST['status'] ?? ''),
        ];
        $errors = [];
        if ($data['name'] === '') $errors[] = 'O nome é obrigatório.';
        if (!in_array($data['client_type'], ['internal', 'external'], true)) $errors[] = 'Selecione um tipo de cliente válido.';
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
        if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Selecione um status válido.';
        if ($errors) { self::form(array_merge($current ?? [], $data), $errors); return; }

        $pdo = Database::connection();
        if ($current) {
            $data['id'] = $current['id'];
            $pdo->prepare('UPDATE clients SET name=:name,client_type=:client_type,document=:document,address=:address,district=:district,city=:city,state=:state,postal_code=:postal_code,contact_name=:contact_name,email=:email,phone=:phone,phone_extension=:phone_extension,status=:status WHERE id=:id')->execute($data);
            $after=$data;unset($after['id']);Auth::registerAudit('client.update', 'clients', (int)$current['id'], $current, $after);
            Flash::set('success', 'Cliente atualizado com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO clients(name,client_type,document,address,district,city,state,postal_code,contact_name,email,phone,phone_extension,status) VALUES(:name,:client_type,:document,:address,:district,:city,:state,:postal_code,:contact_name,:email,:phone,:phone_extension,:status)')->execute($data);
            $id = (int)$pdo->lastInsertId();
            Auth::registerAudit('client.create', 'clients', $id, null, $data);
            Flash::set('success', 'Cliente criado com sucesso.');
        }
        self::redirect('/admin/clients');
    }

    private static function form(?array $client, array $errors): void { self::view('form', compact('client','errors') + ['pageTitle'=>$client ? 'Editar Cliente' : 'Novo Cliente']); }
    private static function requested(): ?array { $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); if(!$id)return null; $s=Database::connection()->prepare('SELECT * FROM clients WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null; }
    private static function view(string $name,array $vars):void { extract($vars);$flash=Flash::pull();$csrf=Csrf::token();$userAuth=Auth::user();require dirname(__DIR__,2).'/Views/admin/clients/'.$name.'.php'; }
    private static function redirect(string $url):never { header('Location: '.$url);exit; }
    private static function notFound():void { http_response_code(404);echo 'Cliente não encontrado.'; }
}
