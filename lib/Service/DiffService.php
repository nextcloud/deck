<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

/**
 * Service for generating visual diffs between two text strings with intelligent word-level diffing
 */
class DiffService {

	/**
	 * Pattern for markdown checkboxes: - [ ] or - [x] or - [X]
	 */
	private const CHECKBOX_PATTERN = '/^(\s*-\s*)\[([ xX])\](.*)/i';

	/**
	 * Pattern for code blocks: ``` or ```language
	 */
	private const CODE_BLOCK_PATTERN = '/^```/';

	/**
	 * Pattern for callout blocks: ::: info, ::: success, ::: warn, ::: error
	 */
	private const CALLOUT_BLOCK_PATTERN = '/^:::\s*(info|success|warn|error)/i';

	/**
	 * Pattern for block endings: :::
	 */
	private const BLOCK_END_PATTERN = '/^:::$/';

	/**
	 * Pattern for blockquotes: > at start of line
	 */
	private const QUOTE_PATTERN = '/^>\s*/';

	/**
	 * Callout block emojis
	 */
	private const CALLOUT_EMOJIS = [
		'info' => 'ℹ️',
		'success' => '✅',
		'warn' => '⚠️',
		'error' => '🔴',
	];

	/**
	 * Generate a visual diff between two text strings
	 *
	 * @param string $oldText The original text
	 * @param string $newText The new text
	 * @return string HTML representation of the diff
	 */
	public function generateDiff(string $oldText, string $newText): string {
		// Convert texts to arrays of lines for comparison
		$oldLines = $this->splitIntoLines($oldText);
		$newLines = $this->splitIntoLines($newText);

		// Get the diff operations using LCS algorithm
		$operations = $this->calculateDiff($oldLines, $newLines);

		// Generate HTML from diff operations with intelligent word-level diffing
		return $this->renderIntelligentDiffHtml($operations, $oldLines, $newLines);
	}

	/**
	 * Split text into lines, preserving empty lines
	 *
	 * @param string $text
	 * @return array
	 */
	private function splitIntoLines(string $text): array {
		if (empty($text)) {
			return [];
		}
		return explode("\n", $text);
	}

	/**
	 * Calculate diff operations using Longest Common Subsequence algorithm
	 *
	 * @param array $oldLines
	 * @param array $newLines
	 * @return array Array of operations: ['type' => 'add|remove|keep', 'old_line' => int, 'new_line' => int]
	 */
	private function calculateDiff(array $oldLines, array $newLines): array {
		$oldCount = count($oldLines);
		$newCount = count($newLines);

		// Build LCS matrix
		$lcs = [];
		for ($i = 0; $i <= $oldCount; $i++) {
			$lcs[$i] = array_fill(0, $newCount + 1, 0);
		}

		// Fill LCS matrix
		for ($i = 1; $i <= $oldCount; $i++) {
			for ($j = 1; $j <= $newCount; $j++) {
				if ($oldLines[$i - 1] === $newLines[$j - 1]) {
					$lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
				} else {
					$lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
				}
			}
		}

		// Backtrack to find the actual diff operations
		return $this->backtrackLCS($lcs, $oldLines, $newLines, $oldCount, $newCount);
	}

	/**
	 * Backtrack through LCS matrix to determine diff operations
	 *
	 * @param array $lcs The LCS matrix
	 * @param array $oldLines
	 * @param array $newLines
	 * @param int $i Current position in old lines
	 * @param int $j Current position in new lines
	 * @return array
	 */
	private function backtrackLCS(array $lcs, array $oldLines, array $newLines, int $i, int $j): array {
		$operations = [];

		while ($i > 0 || $j > 0) {
			if ($i > 0 && $j > 0 && $oldLines[$i - 1] === $newLines[$j - 1]) {
				// Lines are the same, keep them
				array_unshift($operations, [
					'type' => 'keep',
					'old_line' => $i - 1,
					'new_line' => $j - 1
				]);
				$i--;
				$j--;
			} elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
				// Line was added
				array_unshift($operations, [
					'type' => 'add',
					'new_line' => $j - 1
				]);
				$j--;
			} elseif ($i > 0 && ($j === 0 || $lcs[$i][$j - 1] < $lcs[$i - 1][$j])) {
				// Line was removed
				array_unshift($operations, [
					'type' => 'remove',
					'old_line' => $i - 1
				]);
				$i--;
			}
		}

		return $operations;
	}

	/**
	 * Render diff operations as HTML with intelligent word-level diffing
	 *
	 * @param array $operations
	 * @param array $oldLines
	 * @param array $newLines
	 * @return string
	 */
	private function renderIntelligentDiffHtml(array $operations, array $oldLines, array $newLines): string {
		if (empty($operations)) {
			return '';
		}

		// Handle intelligent word-level diffing for modified lines
		return $this->enhanceWithWordLevelDiff($operations, $oldLines, $newLines);
	}

	/**
	 * Enhance diff with word-level granularity for similar lines
	 *
	 * @param array $operations
	 * @param array $oldLines
	 * @param array $newLines
	 * @return string
	 */
	private function enhanceWithWordLevelDiff(array $operations, array $oldLines, array $newLines): string {
		// Find remove/add pairs that might be line modifications
		// First pass: collect all removes and adds
		$removes = [];
		$adds = [];
		$keeps = [];
		
		foreach ($operations as $op) {
			switch ($op['type']) {
				case 'remove':
					$removes[] = $op;
					break;
				case 'add':
					$adds[] = $op;
					break;
				case 'keep':
					$keeps[] = $op;
					break;
			}
		}
		
		// Second pass: detect moves first (exact content matches at different line positions)
		$enhancedOps = [];
		$moveDetectedAdds = [];
		$moveDetectedRemoves = [];
	
		// Find exact content matches between removes and adds (moves)
		foreach ($removes as $removeIndex => $removeOp) {
			$oldLine = $oldLines[$removeOp['old_line']] ?? '';
			$oldLineNum = $removeOp['old_line'] + 1;
		
			// Skip empty lines for move detection
			if (empty(trim($oldLine))) {
				continue;
			}
		
			// Look for exact match in adds
			foreach ($adds as $addIndex => $addOp) {
				if (in_array($addIndex, $moveDetectedAdds)) {
					continue;
				}
			
				$newLine = $newLines[$addOp['new_line']] ?? '';
				$newLineNum = $addOp['new_line'] + 1;
			
				// Exact content match but different line positions = move
				if (trim($oldLine) === trim($newLine) && $oldLineNum !== $newLineNum) {
					$enhancedOps[] = [
						'type' => 'move',
						'old_line' => $removeOp['old_line'],
						'new_line' => $addOp['new_line'],
						'content' => $newLine
					];
					$moveDetectedAdds[] = $addIndex;
					$moveDetectedRemoves[] = $removeIndex;
					break; // Found a move for this remove, stop looking
				}
			}
		}
	
		// Third pass: detect modifications from remaining removes/adds
		$usedAdds = $moveDetectedAdds; // Start with adds already used in moves
		$usedRemoves = $moveDetectedRemoves; // Start with removes already used in moves

		// Process remaining removes and try to find matching adds for modifications
		foreach ($removes as $removeIndex => $removeOp) {
			// Skip removes already used in moves
			if (in_array($removeIndex, $usedRemoves)) {
				continue;
			}

			$bestMatch = null;
			$bestScore = -1;
			$bestAddIndex = -1;

			$oldLine = $oldLines[$removeOp['old_line']] ?? '';
			$oldLineNum = $removeOp['old_line'] + 1;

			// Look for best matching add operation
			foreach ($adds as $addIndex => $addOp) {
				if (in_array($addIndex, $usedAdds)) {
					continue;
				}

				$newLine = $newLines[$addOp['new_line']] ?? '';
				$newLineNum = $addOp['new_line'] + 1;

				// Calculate matching score
				$score = 0;

				// Same line number gets highest priority
				if ($oldLineNum === $newLineNum) {
					$score += 100;
				}

				// Similar content gets secondary priority
				if ($this->shouldUseWordLevelDiff($oldLine, $newLine)) {
					$maxLen = max(strlen($oldLine), strlen($newLine));
					$distance = levenshtein($oldLine, $newLine);
					$similarity = 1 - ($distance / $maxLen);
					$score += $similarity * 50; // Up to 50 points for similarity
				}

				// Proximity bonus (closer line numbers get bonus)
				$proximityBonus = max(0, 10 - abs($oldLineNum - $newLineNum));
				$score += $proximityBonus;

				if ($score > $bestScore) {
					$bestScore = $score;
					$bestMatch = $addOp;
					$bestAddIndex = $addIndex;
				}
			}

			// If we found a good match, create a modify operation
			if ($bestMatch && $bestScore > 10) { // Minimum threshold
				$enhancedOps[] = [
					'type' => 'modify',
					'old_line' => $removeOp['old_line'],
					'new_line' => $bestMatch['new_line']
				];
				$usedAdds[] = $bestAddIndex;
				$usedRemoves[] = $removeIndex;
			} else {
				// No good match, keep as remove
				$enhancedOps[] = $removeOp;
				$usedRemoves[] = $removeIndex;
			}
		}
		
		// Fourth pass: add remaining unused operations
		// Add remaining unused add operations (not involved in moves or modifications)
		foreach ($adds as $addIndex => $addOp) {
			if (!in_array($addIndex, $usedAdds)) {
				$enhancedOps[] = $addOp;
			}
		}
		
		// Add remaining unused remove operations (not involved in moves or modifications)
		foreach ($removes as $removeIndex => $removeOp) {
			if (!in_array($removeIndex, $usedRemoves)) {
				$enhancedOps[] = $removeOp;
			}
		}
		
		// Add keep operations (though we skip them in rendering)
		foreach ($keeps as $keepOp) {
			$enhancedOps[] = $keepOp;
		}

		// Now rebuild HTML with only changed lines and line number prefixes
		// Format each operation for display, using actual line positions in NEW text
		$lines = [];

		foreach ($enhancedOps as $operation) {
			switch ($operation['type']) {
				case 'add':
					$line = $newLines[$operation['new_line']] ?? '';
					$newLineNumber = $operation['new_line'] + 1; // 1-based line numbers
					// Skip empty line additions
					if (!empty(trim($line))) {
						$formatted = $this->formatSpecialLine($line);
						if ($formatted !== null) {
							$lines[] = '✨' . $newLineNumber . ' <ins>' . $formatted . '</ins>';
						}
					}
					break;
				case 'remove':
					$line = $oldLines[$operation['old_line']] ?? '';
					$oldLineNumber = $operation['old_line'] + 1; // 1-based line numbers
					// Skip empty line removals
					if (!empty(trim($line))) {
						$formatted = $this->formatSpecialLine($line);
						if ($formatted !== null) {
							// Show old line number with strikethrough to indicate it's from old version
							$lines[] = '🗑️<del>' . $oldLineNumber . '</del> <del>' . $formatted . '</del>';
						}
					}
					break;
				case 'keep':
					// Skip unchanged lines - don't include them in the output
					break;
				case 'modify':
					$oldLine = $oldLines[$operation['old_line']] ?? '';
					$newLine = $newLines[$operation['new_line']] ?? '';
					$newLineNumber = $operation['new_line'] + 1; // 1-based line numbers
					$lines[] = '✏️' . $newLineNumber . ' ' . $this->generateWordLevelDiff($oldLine, $newLine);
					break;
				case 'move':
					$oldLineNum = $operation['old_line'] + 1;
					$newLineNum = $operation['new_line'] + 1;
					$content = htmlspecialchars($operation['content'], ENT_QUOTES, 'UTF-8');
					$lines[] = '🚚' . $newLineNum . ' (from ' . $oldLineNum . ') ' . $content;
					break;
			}
		}
		
		// Join all lines for display
		if (empty($lines)) {
			return '';
		}

		// Concatenate lines with bullet separator for better readability in single line
		return implode(' | ', $lines);
	}

	/**
	 * Determine if two lines are similar enough to warrant word-level diffing
	 *
	 * @param string $oldLine
	 * @param string $newLine
	 * @return bool
	 */
	private function shouldUseWordLevelDiff(string $oldLine, string $newLine): bool {
		// Don't do word-level diff for very short lines or very different lines
		if (strlen($oldLine) < 3 || strlen($newLine) < 3) {
			return false;
		}
		
		// Calculate similarity using Levenshtein distance
		$maxLen = max(strlen($oldLine), strlen($newLine));
		$distance = levenshtein($oldLine, $newLine);
		$similarity = 1 - ($distance / $maxLen);
		
		// Use word-level diff if lines are at least 30% similar
		return $similarity >= 0.3;
	}

	/**
	 * Generate word-level diff for two similar lines
	 *
	 * @param string $oldLine
	 * @param string $newLine
	 * @return string
	 */
	private function generateWordLevelDiff(string $oldLine, string $newLine): string {
		// Handle special cases first
		if ($this->isCheckboxChange($oldLine, $newLine)) {
			return $this->generateCheckboxDiff($oldLine, $newLine);
		}
		
		// Split lines into words for comparison
		$oldWords = $this->splitIntoWords($oldLine);
		$newWords = $this->splitIntoWords($newLine);
		
		// Get word-level diff operations
		$wordOps = $this->calculateDiff($oldWords, $newWords);
		
		// Render word-level diff
		return $this->renderWordLevelHtml($wordOps, $oldWords, $newWords);
	}

	/**
	 * Check if this is a checkbox toggle change
	 *
	 * @param string $oldLine
	 * @param string $newLine
	 * @return bool
	 */
	private function isCheckboxChange(string $oldLine, string $newLine): bool {
		preg_match(self::CHECKBOX_PATTERN, $oldLine, $oldMatches);
		preg_match(self::CHECKBOX_PATTERN, $newLine, $newMatches);

		// Both lines must be checkboxes with different states
		// We only require same prefix and different state - suffix can change
		return !empty($oldMatches) && !empty($newMatches) &&
			   $oldMatches[1] === $newMatches[1] && // Same prefix (indentation and dash)
			   $oldMatches[2] !== $newMatches[2];   // Different checkbox state
	}

	/**
	 * Generate diff specifically for checkbox changes
	 *
	 * @param string $oldLine
	 * @param string $newLine
	 * @return string
	 */
	private function generateCheckboxDiff(string $oldLine, string $newLine): string {
		preg_match(self::CHECKBOX_PATTERN, $oldLine, $oldMatches);
		preg_match(self::CHECKBOX_PATTERN, $newLine, $newMatches);

		$prefix = $oldMatches[1];
		$oldSuffix = $oldMatches[3];
		$newSuffix = $newMatches[3];

		// Convert checkbox states to checkbox symbols
		$oldCheckbox = (trim(strtolower($oldMatches[2])) === 'x') ? '☑️' : '🔲';
		$newCheckbox = (trim(strtolower($newMatches[2])) === 'x') ? '☑️' : '🔲';

		// If suffix changed too, show that as well
		if ($oldSuffix !== $newSuffix) {
			return $prefix . $oldCheckbox . '→' . $newCheckbox . ' ' . $this->generateWordLevelDiff($oldSuffix, $newSuffix);
		}

		// Show clean transition without del/ins tags on the checkboxes themselves
		return $prefix . $oldCheckbox . '→' . $newCheckbox . $oldSuffix;
	}

	/**
	 * Format special lines (code blocks, callouts, quotes) with emojis
	 *
	 * @param string $line
	 * @return string|null Returns formatted string or null if line should be ignored
	 */
	private function formatSpecialLine(string $line): ?string {
		$trimmed = trim($line);

		// Ignore block ending markers
		if (preg_match(self::BLOCK_END_PATTERN, $trimmed)) {
			return null;
		}

		// Format code block markers
		if (preg_match(self::CODE_BLOCK_PATTERN, $trimmed)) {
			return '→📝';
		}

		// Format callout block markers
		if (preg_match(self::CALLOUT_BLOCK_PATTERN, $trimmed, $matches)) {
			$type = strtolower($matches[1]);
			$emoji = self::CALLOUT_EMOJIS[$type] ?? 'ℹ️';
			return '→' . $emoji;
		}

		// Format blockquotes
		if (preg_match(self::QUOTE_PATTERN, $trimmed)) {
			// Remove the > marker and return the quoted text with emoji
			$quotedText = preg_replace(self::QUOTE_PATTERN, '', $line);
			return '→💬 ' . htmlspecialchars($quotedText, ENT_QUOTES, 'UTF-8');
		}

		// Return original line if not a special pattern
		return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Split text into words while preserving important separators
	 *
	 * @param string $text
	 * @return array
	 */
	private function splitIntoWords(string $text): array {
		// Split on whitespace but preserve the separators
		$words = [];
		$tokens = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		foreach ($tokens as $token) {
			if (!empty($token)) {
				$words[] = $token;
			}
		}
		
		return $words;
	}

	/**
	 * Render word-level diff operations as HTML
	 *
	 * @param array $operations
	 * @param array $oldWords
	 * @param array $newWords
	 * @return string
	 */
	private function renderWordLevelHtml(array $operations, array $oldWords, array $newWords): string {
		$html = '';
		$lastWasDel = false;
		
		foreach ($operations as $operation) {
			switch ($operation['type']) {
				case 'add':
					$word = $newWords[$operation['new_line']] ?? '';
					// Add arrow if previous operation was a deletion
					if ($lastWasDel) {
						$html .= '→<ins>' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</ins>';
					} else {
						$html .= '<ins>' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</ins>';
					}
					$lastWasDel = false;
					break;
				case 'remove':
					$word = $oldWords[$operation['old_line']] ?? '';
					$html .= '<del>' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</del>';
					$lastWasDel = true;
					break;
				case 'keep':
					$word = $oldWords[$operation['old_line']] ?? '';
					$html .= htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
					$lastWasDel = false;
					break;
			}
		}
		
		return $html;
	}
}
