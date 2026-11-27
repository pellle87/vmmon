-- v37: fri rutnätslayout på dashen (kortens positioner per användare och vy)
ALTER TABLE users ADD COLUMN card_pos TEXT NULL;
