<?php
/**
 * Live Helper Chat IA Worker
 *
 * */
class erLhcoreClassLhcaibotWorker
{
    public function __construct()
    {

    }

    public function perform()
    {
        $db = ezcDbInstance::get();
        $db->reconnect(); // Because it timeouts automatically, this calls to reconnect to database, this is implemented in 2.52v

        $chatId = $this->args['chat_id'];

        $chat = erLhcoreClassModelChat::fetch($chatId);

        // chat does not exits, we can exit
        if (!($chat instanceof erLhcoreClassModelChat)) {
            return;
        }

        $chatVariables = $chat->chat_variables_array;

        $ext = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionLhcaibot');

        echo "asdasd";

        try {
            $bot = erLhcoreClassModelGenericBotBot::fetch($chatVariables['gbot_id']);
            if ($this->args['action'] == 'ai_resolve') {

                // Do your stuff here
                /*$ext->executeRequest(array(
                    'chat_id' => $chat->id,
                    'payload' => $this->args['payload']
                ),
                    "/api/payload"
                );*/

                $validPayload = true;

                // Remove event
                $event = erLhcoreClassModelGenericBotChatEvent::fetch($this->args['event_id']);

                if ($event instanceof erLhcoreClassModelGenericBotChatEvent) {
                    $event->removeThis();
                }

                $this->processTrigger($validPayload, $chat, array('args' => array('replace_array' => array('{response_text}' => $this->args['payload']))));
            }

        } catch (Exception $e) {

            $msg = new erLhcoreClassModelmsg();
            $msg->msg = $e->getMessage() . $e->getTraceAsString();
            $msg->chat_id = $chat->id;
            $msg->user_id = - 1;
            $msg->time = time();
            erLhcoreClassChat::getSession()->save($msg);

            // Set last message id and stop bot
            $chat->last_msg_id = $msg->id;
            $chat->status = 0;

            self::setLastOperatorMessage($chat);
        }
    }

    private function processTrigger($valid, & $chat, $paramsTrigger = array())
    {
        if ($valid == true) {
            if (isset($this->args['render_args']['valid']) && is_numeric($this->args['render_args']['valid'])) {
                $trigger = erLhcoreClassModelGenericBotTrigger::fetch($this->args['render_args']['valid']);
                if ($trigger instanceof erLhcoreClassModelGenericBotTrigger) {
                    erLhcoreClassGenericBotWorkflow::processTrigger($chat, $trigger, true, $paramsTrigger);
                }
            }
        } else {
            if (isset($this->args['render_args']['invalid']) && is_numeric($this->args['render_args']['invalid'])) {
                $trigger = erLhcoreClassModelGenericBotTrigger::fetch($this->args['render_args']['invalid']);
                if ($trigger instanceof erLhcoreClassModelGenericBotTrigger) {
                    erLhcoreClassGenericBotWorkflow::processTrigger($chat, $trigger, true, $paramsTrigger);
                }
            }
        }
    }

    // Just set last operator message
    public static function setLastOperatorMessage($chat) {
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('UPDATE lh_chat SET status = :status, last_msg_id = :last_msg_id, last_op_msg_time = :last_op_msg_time, has_unread_op_messages = :has_unread_op_messages, unread_op_messages_informed = 0 WHERE id = :id');
        $stmt->bindValue(':last_msg_id', $chat->last_msg_id, PDO::PARAM_INT);
        $stmt->bindValue(':id', $chat->id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $chat->status, PDO::PARAM_INT);
        $stmt->bindValue(':last_op_msg_time',time(),PDO::PARAM_INT);
        $stmt->bindValue(':has_unread_op_messages',$chat->has_unread_op_messages,PDO::PARAM_INT);
        $stmt->execute();
    }
}
