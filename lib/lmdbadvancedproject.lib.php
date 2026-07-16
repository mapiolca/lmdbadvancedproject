<?php
/* Copyright (C) 2022 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lmdbadvancedproject/lib/lmdbadvancedproject.lib.php
 * \ingroup lmdbadvancedproject
 * \brief   Library file with common functions for Advanced Project.
 */

/**
 * Prepare admin pages header.
 *
 * @return array
 */
function lmdbadvancedprojectAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('lmdbadvancedproject@lmdbadvancedproject');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/lmdbadvancedproject/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbadvancedproject/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/lmdbadvancedproject/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'lmdbadvancedproject@lmdbadvancedproject');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'lmdbadvancedproject@lmdbadvancedproject', 'remove');

	return $head;
}
