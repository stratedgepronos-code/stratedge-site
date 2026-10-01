-- Ajouter la colonne locked_image_path à la table bets
ALTER TABLE bets ADD COLUMN locked_image_path VARCHAR(255) NULL DEFAULT NULL AFTER image_path;
