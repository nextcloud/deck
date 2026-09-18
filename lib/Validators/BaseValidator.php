<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Validators;

use Exception;
use OCA\Deck\BadRequestException;
use OCA\Deck\Model\OptionalNullableValue;

abstract class BaseValidator {

	/**
	 * @return array
	 */
	abstract public function rules();

	/**
	 * Validate given entries
	 *
	 * @param array $data
	 * @return void
	 * @throws BadRequestException
	 */
	private function validate($data) {
		$rules = $this->rules();

		foreach ($data as $field => $value) {
			$field_rule = $rules[$field];
			$isOptional = is_array($field_rule) && in_array('optional', $field_rule, true);

			if ($isOptional) {
				if ($value instanceof OptionalNullableValue) {
					$value = $value->getValue();
				}
				if ($value === null) {
					continue;
				}
			}

			if (is_array($field_rule)) {
				foreach ($field_rule as $rule) {
					if ($rule === 'optional') {
						continue;
					}
					// The format for specifying validation rules and parameters follows an
					// easy {rule}:{parameters} formatting convention. For instance the
					// rule "Max:3" states that the value may only be three letters.
					if (str_contains($rule, ':')) {
						[$rule, $parameter] = explode(':', $rule, 2);
						if (!$this->{$rule}($value, $parameter)) {
							throw new BadRequestException(
								$this->getErrorMessage($rule, $field, $parameter, $isOptional));
						}
					} else {
						if (!$this->{$rule}($value)) {
							throw new BadRequestException(
								$this->getErrorMessage($rule, $field, null, $isOptional));
						}
					}
				}
			}

			if (is_callable($field_rule) && !$field_rule($value)) {
				throw new BadRequestException($field . ' must be provided');
			}
		}
	}

	/**
	 * @param array $data
	 * @return void
	 * @throws BadRequestException
	 */
	public function check(array $data) {
		$this->validate($data);
	}

	/**
	 * @param $value
	 * @return bool
	 */
	private function numeric($value): bool {
		return is_numeric($value);
	}

	/**
	 * @param $value
	 * @return bool
	 */
	private function bool($value): bool {
		return is_bool($value);
	}

	/**
	 * @param $value
	 * @return bool
	 */
	private function not_false($value): bool {
		return ($value !== false) && ($value !== 'false');
	}

	/**
	 * @param $value
	 * @return bool
	 */
	private function not_null($value): bool {
		return !is_null($value);
	}

	/**
	 * @param $value
	 * @return bool
	 */
	private function not_empty($value): bool {
		// Treat string zero as a valid non-empty value while still rejecting other empty values
		if ($value === '0') {
			return true;
		}

		if (is_string($value)) {
			return strlen(trim($value)) > 0;
		}
		return !empty($value);
	}

	/**
	 * @throws Exception
	 */
	private function max($value, $limit): bool {
		if (!$limit || !is_numeric($limit)) {
			throw new Exception('Validation rule max requires at least 1 parameter. ' . json_encode($limit));
		}
		return $this->getSize($value) <= $limit;
	}

	/**
	 * @throws Exception
	 */
	private function min($value, $limit): bool {
		if (!$limit || !is_numeric($limit)) {
			throw new Exception('Validation rule max requires at least 1 parameter.');
		}
		return $this->getSize($value) >= $limit;
	}

	/**
	 * @throws Exception
	 */
	private function datetime(mixed $value): bool {
		if (!is_string($value) || $value === '') {
			return false;
		}

		$allowedFormats = [
			'Y-m-d',
			'Y-m-d H:i:s',
			'Y-m-d\TH:i:sP',
			'Y-m-d\TH:i:s.v\Z',
		];

		foreach ($allowedFormats as $format) {
			$datetime = \DateTime::createFromFormat($format, $value);
			if ($datetime && $datetime->format($format) === $value) {
				// Check if the year is within the valid range
				if ((int)$datetime->format('Y') > 9999) {
					return false;
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the size of an attribute.
	 *
	 * @param mixed $value
	 * @return int
	 */
	protected function getSize($value): int {
		// This method will determine if the attribute is a number or string and
		// return the proper size accordingly. If it is a number, then number itself
		// is the size.
		if (is_int($value)) {
			return $value;
		} elseif (is_array($value)) {
			return count($value);
		}

		return mb_strlen($value ?? '');
	}

	/**
	 * @param $field
	 * @param $parameter
	 * @return string
	 */
	protected function getErrorMessage(string $rule, $field, $parameter = null, $isOptional = false): string {
		if (in_array($rule, ['max', 'min'], true)) {
			return $rule === 'max'
			? $field . ' cannot be longer than ' . $parameter . ' characters '
			: $field . ' must be at least ' . $parameter . ' characters long ';
		}

		if ($isOptional) {
			return $field . ' must be ' . str_replace('_', ' ', $rule);
		}

		return $field . ' must be provided and must be ' . str_replace('_', ' ', $rule);
	}
}
