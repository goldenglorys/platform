<?php

namespace Ushahidi\App\Http\Controllers\API;

use Ushahidi\App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ushahidi\App\Http\Controllers\RESTController;

/**
 * Demo HXL feature flag
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application\Controllers
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

class HXLController extends RESTController
{

	protected function getResource()
	{
		return 'hxl';
	}

	/**
	 * Retrieve a basic information about the API
	 *
	 * GET /api
	 *
	 * @return void
	 */
	public function index(Request $request)
	{
		return [
			'hxl'       => 'YAY!'
		];
	}
}
