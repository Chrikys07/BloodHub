-- Foto opcional do perfil do usuario. Caminho relativo publico, sem binarios no banco.
ALTER TABLE users ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER email;
