<?php
/*
 *  Copyright notice
 *
 * (c) 2010 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  			Daniel Corn <cod@iresults.li>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * The iresults error class extends a PHP exception with an additional user info
 * property to allow more information to be transported with the error.
 *
 * @author	Daniel Corn <cod@iresults.li>
 * @package	Iresults
 * @subpackage	Iresults
 */
class Exception_ScssException extends Exception {
	/**
	 * The additional information transported with this error.
	 *
	 * @var array<mixed>
	 */
	protected $userInfo = array();

	/**
	 * Gets the user info dictionary.
	 * @return	array<mixed>
	 */
	public function getUserInfo() {
		return $this->userInfo;
	}

	/**
	 * Setter for userInfo
	 *
	 * @param	array<mixed> $newValue The new value to set
	 * @return	void
	 * @internal
	 */
	public function _setUserInfo($newValue) {
		$this->userInfo = $newValue;
	}


	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* FACTORY METHODS   MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * Factory method: Returns a new error with the given message, code and
	 * user information.
	 *
	 * @return	Tx_Iresults_Error
	 */
	static public function errorWithMessageCodeAndUserInfo($message, $code = 0, $userInfo = array()) {
		$error = NULL;
		if (IR_MODERN_PHP) {
			$calledClass = get_called_class();
			$error = new $calledClass($message, $code);
		} else {
			$error = new self($message, $code);
		}
		$error->_setUserInfo($userInfo);
		return $error;
	}
}
?>