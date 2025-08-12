-- ============================================================================
--
-- Copyright (C) 2012-2023      Francis Appels        <francis.appels@z-application.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ============================================================================


CREATE TABLE llx_extdirect_user(
  rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL ,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user integer DEFAULT NULL,
  app_id varchar(50) NOT NULL,
  app_name varchar(50) NOT NULL,
  ack_id varchar(50) DEFAULT NULL,
  requestid varchar(50) DEFAULT NULL,
  datec datetime default NULL,
  date_last_connect datetime DEFAULT NULL,
  dev_platform varchar(50) DEFAULT NULL,
  dev_type varchar(50) DEFAULT NULL,
  webview_name varchar(100) DEFAULT NULL,
  webview_version varchar(100) DEFAULT NULL,
  identify smallint DEFAULT 0,
  inventory_mode smallint DEFAULT 0,
  entity integer DEFAULT NULL,
  import_key varchar(14)
) ENGINE=innodb;
