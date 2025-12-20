-- Add available_spots column to parkings table
ALTER TABLE parkings
ADD COLUMN available_spots INT NOT NULL DEFAULT 0
AFTER total_spaces;

-- Initialize available_spots with total_spaces value
UPDATE parkings
SET available_spots = total_spaces
WHERE available_spots = 0;
