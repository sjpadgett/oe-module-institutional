-- DEV ONLY: Seed minimal demo data for oe-module-institutional
-- Adjust @FACILITY_ID if your dev facility id is not 1.
SET @FACILITY_ID := 1;

-- Locations (beds/rooms)
INSERT INTO oei_location (facility_id, code, name, location_type, unit_name, is_active, sort_order, notes)
VALUES
(@FACILITY_ID, 'ED01', 'ED Room 1', 'ROOM', 'ED', 1, 10, 'Seed'),
(@FACILITY_ID, 'ED02', 'ED Room 2', 'ROOM', 'ED', 1, 20, 'Seed'),
(@FACILITY_ID, 'OBS1', 'Obs Bay 1', 'OBS', 'OBS', 1, 30, 'Seed')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  location_type = VALUES(location_type),
  unit_name = VALUES(unit_name),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

-- Facility directory entries (receiving)
INSERT INTO oei_facility_directory (facility_id, name, service_type, phone, fax, email, address, hours, notes, is_active, sort_order)
VALUES
(@FACILITY_ID, 'Regional Hospital ICU', 'ICU', NULL, NULL, NULL, NULL, NULL, 'Seed', 1, 10),
(@FACILITY_ID, 'Behavioral Health Receiving', 'BH', NULL, NULL, NULL, NULL, NULL, 'Seed', 1, 20)
ON DUPLICATE KEY UPDATE
  service_type = VALUES(service_type),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);


