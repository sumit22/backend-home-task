<?php

namespace App\Contract;

use App\Entity\RepositoryScan;
use App\Entity\Rule;

/**
 * RuleEvaluatorInterface
 * 
 * Interface for rule evaluation strategies (SOLID: Interface Segregation Principle)
 * 
 * This allows:
 * - Different evaluation strategies for different trigger types
 * - Easy to add new trigger types without modifying existing code
 * - Each evaluator focuses on single responsibility
 * - Testable in isolation
 */
interface RuleEvaluatorInterface
{
    /**
     * Check if this evaluator supports the given trigger type
     * 
     * @param string $triggerType The trigger type to check
     * @return bool True if this evaluator can handle the trigger type
     */
    public function supports(string $triggerType): bool;
    
    /**
     * Evaluate if the rule matches the scan
     * 
     * @param Rule $rule The rule to evaluate
     * @param RepositoryScan $scan The scan to check against
     * @return bool True if the rule matches (should fire)
     */
    public function evaluate(Rule $rule, RepositoryScan $scan): bool;
    
    /**
     * Get the trigger type this evaluator handles
     * 
     * @return string The trigger type identifier
     */
    public function getTriggerType(): string;
}
