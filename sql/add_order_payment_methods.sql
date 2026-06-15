USE concert_system;

DELIMITER $$

DROP PROCEDURE IF EXISTS ensure_order_checkout_columns $$

CREATE PROCEDURE ensure_order_checkout_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'Orders'
          AND COLUMN_NAME = 'payment_method'
    ) THEN
        ALTER TABLE Orders ADD COLUMN payment_method VARCHAR(30) NULL AFTER status;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'Orders'
          AND COLUMN_NAME = 'delivery_method'
    ) THEN
        ALTER TABLE Orders ADD COLUMN delivery_method VARCHAR(30) NULL AFTER payment_method;
    END IF;
END $$

CALL ensure_order_checkout_columns() $$
DROP PROCEDURE ensure_order_checkout_columns $$

DELIMITER ;
