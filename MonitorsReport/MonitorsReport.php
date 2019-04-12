<?php

# Copyright (c) 2019 Aantoly Kabakov (anatoly.kabakov.inbev@gmail.com), Ruzhelovich Vladimir (ruzhelovich.vladimir@gmail.com)

# Download files for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 3 of the License, or (at your option) any later version.
#
# Download files plugin for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Inline column configuration plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

class MonitorsReportPlugin extends MantisPlugin {

	public function register() {
		$this->name = plugin_lang_get("title");
		$this->description = plugin_lang_get("description");

		$this->version = "1.0";

		if( version_compare( MANTIS_VERSION, '1.3', '<') ) {
			$this->requires = array(
				'MantisCore' => '2.0, < 3.0',
			);
		}

		$this->author = 'Anatoly Kabakov';
		$this->contact = 'anatoly.kabakov.inbev@gmail.com';
    }

    public function hooks() {

		return array (
			'EVENT_MENU_FILTER' => 'add_report_problems_link',
		);
	}

	public function add_report_problems_link() {
		return array( '<a class="btn btn-sm btn-primary btn-white btn-round" href="' . plugin_page( 'excel_xml_export' ) . '">' . plugin_lang_get("title")  . '</a>', );
    }
}
