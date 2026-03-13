-- Exécuter une fois en prod si la promotion admin ne persiste pas ou si la colonne role est en ENUM.
-- Assure que la colonne role accepte admin_tennis, admin_fun_sport, etc.
ALTER TABLE membres MODIFY COLUMN role VARCHAR(30) DEFAULT NULL;
