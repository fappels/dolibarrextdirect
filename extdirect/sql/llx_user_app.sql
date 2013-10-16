-- ============================================================================
-- 
-- Copyright (C) 2012      Francis Appels        <francis.appels@z-application.com>
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


CREATE TABLE `llx_user_app` (
  `rowid` int(11) NOT NULL auto_increment PRIMARY KEY,
  `fk_user` int(11) default NULL,
  `app_id` varchar(50) NOT NULL COMMENT 'app uuid',
  `app_name` varchar(50) NOT NULL,
  `ack_id` varchar(50) default NULL COMMENT 'Acknowledge id to send back to app',
  `requestid` varchar(50) default NULL COMMENT 'Identification of the connectionon requestor',
  `datec` datetime default NULL,
  `date_last_connect` datetime default NULL,
  `dev_platform` varchar(50) default NULL,
  `dev_type` varchar(50) default NULL
) ENGINE=innodb;

