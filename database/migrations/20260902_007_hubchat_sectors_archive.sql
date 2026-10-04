-- HubChat: participantes externos de setores e ocultacao pessoal de privadas.
ALTER TABLE chat_participants
    ADD COLUMN IF NOT EXISTS participant_source ENUM('unit','external') NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME(6) NULL AFTER last_read_at,
    ADD INDEX IF NOT EXISTS idx_chat_participants_archived (user_id, archived_at, conversation_id);

-- Os participantes dos grupos criados pela versao anterior vieram de user_units.
UPDATE chat_participants cp
JOIN chat_conversations c ON c.id=cp.conversation_id AND c.type='group'
JOIN user_units uu ON uu.unit_id=c.unit_id AND uu.user_id=cp.user_id
SET cp.participant_source='unit'
WHERE cp.participant_source IS NULL;
