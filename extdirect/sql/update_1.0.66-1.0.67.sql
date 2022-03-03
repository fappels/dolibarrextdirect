-- Copyright (C) 2021 Francis Appels <francis.appels@z-application.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see http://www.gnu.org/licenses/.

ALTER TABLE llx_extdirect_user CHANGE COLUMN tms tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_extdirect_activity CHANGE COLUMN tms tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_extdirect_user ADD COLUMN identify smallint default 0 COMMENT 'User need to indentify on app to login';
ALTER TABLE llx_extdirect_user ADD COLUMN webview_name varchar(100) default NULL;
ALTER TABLE llx_extdirect_user ADD COLUMN webview_version varchar(100) default NULL;