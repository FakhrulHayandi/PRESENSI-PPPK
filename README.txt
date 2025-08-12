Presensi PPPK - Backend package

Files included:
- index.html (pegawai) - capture selfie + GPS and POST to upload.php
- admin.html (admin dashboard) - view attendance and export CSV
- upload.php - receive uploads, save image to /uploads, write to MySQL
- get_data.php - return JSON of attendance (filter by date)
- db_config.php - database config (edit before use)
- presensi_table.sql - SQL to create database/table
- uploads/ - folder where images will be stored (create and writeable)

Setup steps are in presensi_table.sql and README. Ensure PHP environment and MySQL are available.
