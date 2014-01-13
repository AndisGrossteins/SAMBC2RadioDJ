Migrate SAM Broadcaster data to RadioDJ
===============================

While managing SAM Broadcaster for [Radio H2O](http://radioh2o.lv/ "Radio H2O") I got sick of all the bugs and charset incompatibilities between filesystem and Firebird DB dealing with other SAMBC quirks. So, I decided to migrate SAMBC data to MySQL to see if it could help.  In short; it didn’t solve main problem, because SAMBC messes up everything by using Windows default code page for its internal workings. Never the less, it was a nice exercise in coding for two databases and charset conversion. That project is located at [Migrate-SAMBC-Firebird-to-MySQL](https://github.com/AndisGrossteins/Migrate-SAMBC-Firebird-to-MySQL "Migrate-SAMBC-Firebird-to-MySQL"). It has some issues and typos in comments but it works.

Now I give you...

## Migrate SAM Broadcaster data to RadioDJ ##

The idea had been on my mind for some time, but after reading a [RadioDJ forum post by NounosSon](http://www.radiodj.ro/community/index.php?topic=4460.0 "RadioDJ forum post by NounosSon") I decided to realise it. After all - how hard can it be; It is only data after all.

This script generates a valid MySQL SQL script from SAMBC Firebird database if `WORK_MODE` in `config.php` is set to `WORK_MODE_FILE`
or inserts data from SAMBC Firebird or MySQL directly into RDJ MySQL database if `WORK_MODE` is set to `WORK_MODE_INSERT`.
In latter case you have to make sure the database and its tables exist.
I highly suggest to try `WORK_MODE_FILE` first, so you don't mess up you RDJ database.
While exporting data it is possible to convert between character sets if `SAM_CHARSET` and `TARGET_CHARSET` are set to different values.

__Note:__ Charset conversion can be disable by setting `SAM_CHARSET` and `CHARESET` to same value.

### Requirements: ###

1. Recent PHP version. I'm currently using PHP/5.5.1 and have not tested this script on older versions
2. PHP PDO extension enabled http://php.net/manual/en/book.pdo.php

### To run it: ###
One can run it as CLI or from web server.
If you have PHP on your PATH, run it in CLI like this: `php migrate.php`

---------------------------------

### Version 0.1 ###
Exports only `historylist` data from SAMBC.
Few fields are missing from resulting data:

1. `id_subcat` (sub category reference);
2. `disk_no` (no disc numbers in SAMDB);
3. `original_artist`;
4. `copyright`;

I had to fiddle a bit with genre data, but got it working after all. If the genre from SAMBC `songlist` matches genre in RDJ `genre` table the matching `genre_id` will be exported. Comparison is done case-insensitive, so it should be ok if data is different from song to song.

### Version 0.2 ###
Can export `historylist` and `songlist` data from SAMBC.
For now song data will be exported without category data. The problem is that RadioDJ does not have multilevel categories like SAMBC does.
I had a conversation with Marius about this and he promised to look if and how he could implement that in RDJ.
All SAMBC `xfade` settings will be translated to RDJ `cue_times`, but all songs will have to be placed in correct subcategories.

---------------------------------

### TODO: ###
* Esport SAMBC categories as RadioDJ playlists. If only RDJ rotations allowed to select songs from playlists, I'd be ready to migrate.
* Create RDJ MySQL tables if needed
* Maybe check `songlist` entries for moved/removed files before import
