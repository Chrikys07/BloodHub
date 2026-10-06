<?php
declare(strict_types=1);
namespace BloodHub\Controllers;

use BloodHub\Core\{AdminGuard, Auth, Csrf, Database, Flash};
use PDO;

final class ProfileController
{
    private const MAX_PHOTO_SIZE = 2097152;
    private const MIME_EXTENSIONS = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];

    public static function show(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        self::render();
    }

    public static function update(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('error', 'Sessão expirada. Atualize a página e tente novamente.');
            self::redirect();
        }
        $current = self::currentUser();
        if (!$current) { Auth::logout(); header('Location: /login'); exit; }
        if (($_POST['action'] ?? '') === 'change_password') {
            self::changePassword($current);
        }
        $remove = ($_POST['remove_photo'] ?? '') === '1';
        $upload = $_FILES['photo'] ?? null;
        $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$remove && !$hasUpload) {
            Flash::set('error', 'Selecione uma imagem para atualizar.');
            self::redirect();
        }

        $newPath = $remove ? null : ($current['photo_path'] ?? null);
        $absoluteNew = null;
        if ($hasUpload) {
            $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                Flash::set('error', $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ? 'A imagem excede o limite de 2 MB.' : 'Não foi possível receber a imagem. Tente novamente.');
                self::redirect();
            }
            if ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > self::MAX_PHOTO_SIZE) {
                Flash::set('error', 'A imagem deve ter no máximo 2 MB.'); self::redirect();
            }
            $tmp = (string)($upload['tmp_name'] ?? '');
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
            if (!isset(self::MIME_EXTENSIONS[$mime]) || @getimagesize($tmp) === false) {
                Flash::set('error', 'Use uma imagem válida em JPG, PNG ou WebP.'); self::redirect();
            }
            $directory = dirname(__DIR__, 2) . '/public/uploads/avatars';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                Flash::set('error', 'Não foi possível preparar o armazenamento da foto.'); self::redirect();
            }
            $filename = 'user-' . (int)$current['id'] . '-' . bin2hex(random_bytes(12)) . '.' . self::MIME_EXTENSIONS[$mime];
            $absoluteNew = $directory . '/' . $filename;
            if (!move_uploaded_file($tmp, $absoluteNew)) {
                Flash::set('error', 'Não foi possível salvar a foto. Tente novamente.'); self::redirect();
            }
            $newPath = '/uploads/avatars/' . $filename;
        }

        try {
            Database::connection()->prepare('UPDATE users SET photo_path=:photo WHERE id=:id')->execute(['photo'=>$newPath, 'id'=>$current['id']]);
        } catch (\Throwable $e) {
            if ($absoluteNew && is_file($absoluteNew)) @unlink($absoluteNew);
            Flash::set('error', 'Não foi possível atualizar a foto.'); self::redirect();
        }
        if (($remove || $hasUpload) && !empty($current['photo_path'])) self::deleteManagedPhoto((string)$current['photo_path']);
        Auth::registerAudit('profile.photo.update', 'users', (int)$current['id'], ['photo_path'=>$current['photo_path']], ['photo_path'=>$newPath]);
        Auth::refreshUser();
        Flash::set('success', $remove ? 'Foto removida. As iniciais voltarão a ser exibidas.' : 'Foto de perfil atualizada com sucesso.');
        self::redirect();
    }

    private static function currentUser(): ?array
    {
        $stmt=Database::connection()->prepare('SELECT id,name,email,photo_path,status,password_hash FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>(int)(Auth::user()['id'] ?? 0)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function changePassword(array $current): never
    {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmation = (string)($_POST['new_password_confirmation'] ?? '');

        $validationError = self::passwordChangeError(
            (string)$current['password_hash'],
            $currentPassword,
            $newPassword,
            $confirmation
        );
        if ($validationError !== null) {
            Flash::set('error', $validationError);
            self::redirect();
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            Flash::set('error', 'Não foi possível proteger a nova senha. Tente novamente.');
            self::redirect();
        }

        try {
            Database::connection()->prepare('UPDATE users SET password_hash=:password_hash WHERE id=:id')
                ->execute(['password_hash'=>$passwordHash, 'id'=>(int)$current['id']]);
        } catch (\Throwable $e) {
            Flash::set('error', 'Não foi possível alterar a senha. Tente novamente.');
            self::redirect();
        }

        Auth::registerAudit('profile.password.update', 'users', (int)$current['id']);
        Flash::set('success', 'Senha alterada com sucesso. Use a nova senha no próximo login.');
        self::redirect();
    }

    private static function passwordChangeError(string $currentHash, string $currentPassword, string $newPassword, string $confirmation): ?string
    {
        if ($currentPassword === '' || !password_verify($currentPassword, $currentHash)) {
            return 'A senha atual está incorreta.';
        }
        if (strlen($newPassword) < 8) {
            return 'A nova senha deve ter no mínimo 8 caracteres.';
        }
        if ($newPassword !== $confirmation) {
            return 'A nova senha e a confirmação não coincidem.';
        }
        return null;
    }

    private static function deleteManagedPhoto(string $path): void
    {
        if (!preg_match('#^/uploads/avatars/[a-zA-Z0-9._-]+$#', $path)) return;
        $file = dirname(__DIR__, 2) . '/public' . $path;
        if (is_file($file)) @unlink($file);
    }

    private static function render(): void
    {
        $profile = self::currentUser();
        $pageTitle='Meu perfil'; $userAuth=Auth::user(); $flash=Flash::pull(); $csrf=Csrf::token();
        require dirname(__DIR__) . '/Views/profile/show.php';
    }

    private static function redirect(): never { header('Location: /profile'); exit; }
}
