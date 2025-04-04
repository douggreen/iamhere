<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validator for the PublishStateConstraint.
 */
class PublishStateConstraintValidator extends ConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $value->getEntity();

    // No need to validate entities that are not moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return;
    }

    // No need to validate if a moderation state has not been set.
    if ($value->isEmpty()) {
      return;
    }

    // No need to validate when there is no time set.
    if (!isset($entity->publish_on->value)) {
      return;
    }

    $moderation_state = $entity->moderation_state->value;
    $publish_state = $entity->publish_state->value;

    if (!$this->isValidTransition($entity, $moderation_state, $publish_state)) {
      $workflow_type = $this->getEntityWorkflowType($entity);
      $mod_state_label = $workflow_type->hasState($moderation_state) ? $workflow_type->getState($moderation_state)->label() : 'None';
      $pub_state_label = $workflow_type->hasState($publish_state) ? $workflow_type->getState($publish_state)->label() : 'None';
      $this->context
        ->buildViolation($constraint->invalidTransitionMessage, [
          '%publish_state' => $pub_state_label,
          '%content_state' => $mod_state_label,
        ])
        ->atPath('publish_state')
        ->addViolation();
    }
  }

}
