-- ============================================================================
--
-- Copyright (C) 2013-2021      Francis Appels        <francis.appels@z-application.com>
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


CREATE TABLE llx_extdirect_activity(
  rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL ,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user integer DEFAULT NULL,
  app_id varchar(50) NOT NULL,
  app_version varchar(50) DEFAULT NULL,
  activity_name varchar(50) NOT NULL,
  activity_id integer DEFAULT 0 NOT NULL,
  datec datetime DEFAULT NULL,
  status varchar(50) DEFAULT NULL
) ENGINE=innodb;
