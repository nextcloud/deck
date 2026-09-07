<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Validators;

use Exception;
use OCA\Deck\BadRequestException;

abstract class BaseValidator {
	/**
	 * Date formats accepted by the 'date' rule, covering the ISO-8601 output of
	 * the web frontend as well as the formats documented in docs/API.md.
	 */
	private const DATE_FORMATS = [
		'Y-m-d\TH:i:s.v\Z',
		'Y-m-d\TH:i:s.u\Z',
		\DateTimeInterface::ATOM,
		'Y-m-d\TH:i:s',
		'Y-m-d H:i:s',
		'Y-m-d',
	];

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

			if (is_array($field_rule)) {
				foreach ($field_rule as $rule) {
					// The format for specifying validation rules and parameters follows an
					// easy {rule}:{parameters} formatting convention. For instance the
					// rule "Max:3" states that the value may only be three letters.
					if (str_contains($rule, ':')) {
						[$rule, $parameter] = explode(':', $rule, 2);
						if (!$this->{$rule}($value, $parameter)) {
							throw new BadRequestException(
								$this->getErrorMessage($rule, $field, $parameter));
						}
					} else {
						if (!$this->{$rule}($value)) {
							throw new BadRequestException(
								$field . ' must be provided and must be ' . str_replace('_', ' ', $rule));
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
	 * Check that a value is a date that can be stored and read back again.
	 *
	 * The value is matched against an explicit list of accepted formats,
	 * because \DateTime silently misreads out of range input instead of
	 * rejecting it: '12345-01-01' for example is parsed as 2005-01-01 12:34.
	 * Such a value is written to the database but can no longer be parsed
	 * when it is read again, which leaves the card permanently broken.
	 *
	 * An empty value is considered valid so that optional dates can be unset.
	 *
	 * @param $value
	 * @return bool
	 */
	private function date($value): bool {
		if ($value === null || $value === '') {
			return true;
		}

		if (!is_string($value)) {
			return false;
		}

		foreach (self::DATE_FORMATS as $format) {
			if (\DateTimeImmutable::createFromFormat($format, $value) === false) {
				continue;
			}

			// createFromFormat() accepts overflowing values such as month 13
			// and only reports them through the warnings.
			$errors = \DateTimeImmutable::getLastErrors();
			if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
				return true;
			}
		}

		return false;
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
	protected function getErrorMessage(string $rule, $field, $parameter = null): string {
		if (in_array($rule, ['max', 'min'], true)) {
			return $rule === 'max'
			? $field . ' cannot be longer than ' . $parameter . ' characters '
			: $field . ' must be at least ' . $parameter . ' characters long ';
		}

		return $field . ' must be provided and must be ' . str_replace('_', ' ', $rule);
	}
}
