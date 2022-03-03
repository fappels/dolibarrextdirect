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
  rowid int(11) NOT NULL auto_increment PRIMARY KEY, 
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  fk_user int(11) default NULL, 
  app_id varchar(50) NOT NULL COMMENT 'extdirect app id', 
  app_version varchar(50) default NULL, 
  activity_name varchar(50) NOT NULL COMMENT 'Name of activity or module', 
  activity_id int(11) NOT NULL DEFAULT 0 COMMENT 'rowid of module data, ex product or commande', 
  datec datetime default NULL, 
  status varchar(50) default NULL
) ENGINE=innodb;
