<?php

namespace Drupal\privatemsg\Plugin\DevelGenerate;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\devel_generate\DevelGenerateBase;
use Drupal\privatemsg\Entity\PrivatemsgMessage;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a PrivatemsgDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "privatemsg",
 *   label = @Translation("private messages threads"),
 *   description = @Translation("Generate a given number of private messages threads."),
 *   url = "privatemsg",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 50,
 *     "sender_id" = 1,
 *     "recipient_id" = 1,
 *   },
 *   dependencies = {
 *     "privatemsg",
 *   },
 * )
 */
class PrivatemsgDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * Provides system time.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->privateMsgService = $container->get('privatemsg.common');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['num'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of private messages'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $user_ids = [];
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    foreach ($users as $user) {
      $user_ids[] = $user->id();
    }

    $form['sender_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Sender user id'),
      '#default_value' => 1,
      '#options' => $user_ids,
      '#required' => TRUE,
    ];

    $form['recipient_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Recipient user id'),
      '#default_value' => 1,
      '#options' => $user_ids,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(array $form, FormStateInterface $form_state): void {
    if ($form_state->getValue('sender_id') === $form_state->getValue('recipient_id')) {
      $form_state->setErrorByName('recipient_id', $this->t('User cannot send message to himself'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateElements(array $values): void {
    $num = $values['num'];
    $sender_id = $values['sender_id'];
    $recipient_id = $values['recipient_id'];

    for ($i = 1; $i <= $num; $i++) {
      $message = PrivatemsgMessage::create([
        'owner' => $sender_id,
        'message' => [
          'value' => Markup::create($this->getRandom()->sentences(random_int(4, 10), TRUE)),
          'format' => 'full_html',
        ],
      ]);
      $message->save();

      $thread_members = [$sender_id, $recipient_id];

      $group_id = $this->privateMsgService->getLastThreadGroupNumber();
      ++$group_id;

      $subject = $this->getRandom()->sentences(random_int(4, 10), TRUE);

      foreach ($thread_members as $thread_member) {
        $data = [
          'subject' => $subject,
          'members' => $thread_members,
          'owner' => $thread_member,
          'group' => $group_id,
          'private_messages' => $message->id(),
          'updated_custom' => $this->time->getRequestTime(),
        ];
        $thread = $this->entityTypeManager
          ->getStorage('privatemsg_thread')
          ->create($data);
        $thread->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams(array $args, array $options = []): array {
    return [
      'num' => $options['num'],
      'sender_id' => $options['sender_id'],
      'recipient_id' => $options['recipient_id'],
    ];
  }

}
