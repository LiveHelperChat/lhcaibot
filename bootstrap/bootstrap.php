<?php

class erLhcoreClassExtensionLhcaibot {

    public function __construct() {

    }

    public function run() {

        $this->registerAutoload ();

        $dispatcher = erLhcoreClassChatEventDispatcher::getInstance();

        // Handle generic messages payload
        $dispatcher->listen('chat.genericbot_event_handler', array($this,'genericHandlerEvent'));
        $dispatcher->listen('chat.genericbot_get_click_async', array($this,'getClick'));
    }

    public function genericHandlerEvent($params)
    {
        if ($params['render'] == 'ai_resolve') {

            if ($params['event']->counter == 0) {

                // If AI Takes long time to execute it makes sense to have it as background worker.

                // Background execution. Requires lhcphpresque extension.
                // REDIS_BACKEND=localhost:6379 INTERVAL=5 REDIS_BACKEND_DB=1 COUNT=1 VERBOSE=1 QUEUE='lhc_ai_worker' /usr/bin/php resque.php
                if ($this->settings['app_settings']['background_worker'] === true) {

                    erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionLhcphpresque')->enqueue(
                        'lhc_ai_worker',
                        'erLhcoreClassLhcaibotWorker',
                        array(
                            'action' => $params['render'],
                            'chat_id' => $params['chat']->id,
                            'payload' => $params['payload'],
                            'render_args' => $params['render_args'],
                            'event_id' => $params['event']->id
                        )
                    );

                    return array(
                        'status' => erLhcoreClassChatEventDispatcher::STOP_WORKFLOW,
                        'keep_event' => true
                    );

                // Direct execution
                } else {
                    $responseFound = true;

                    // Your logic goes here

                    if ($responseFound == true) {
                        $trigger = erLhcoreClassModelGenericBotTrigger::fetch($params['render_args']['valid']);
                        erLhcoreClassGenericBotWorkflow::processTrigger($params['chat'], $trigger, true, array('args' => array('replace_array' => array('{response_text}' => $params['payload']))));
                    } else {
                        $trigger = erLhcoreClassModelGenericBotTrigger::fetch($params['render_args']['invalid']);
                        erLhcoreClassGenericBotWorkflow::processTrigger($params['chat'], $trigger, true, array());
                    }
                }

            } else {
                if ($this->settings['app_settings']['background_worker'] === true) {
                    return array(
                        'status' => erLhcoreClassChatEventDispatcher::STOP_WORKFLOW,
                        'keep_event' => true
                    );
                }
            }
        }
    }

    public function getClick($params)
    {
        $chatVariables = $params['chat']->chat_variables_array;

        if (isset($chatVariables['gbot_id']) && $chatVariables['gbot_id'] > 0) {

            //echo $params['payload']; // ai_click
            // You can do your stuff here.

            // Now based on response you can find relevant trigger.
            $responseFromAI = 'imaginary_button_response';

            $event = erLhcoreClassGenericBotWorkflow::findTextMatchingEvent($responseFromAI, $chatVariables['gbot_id']);

            if ($event instanceof erLhcoreClassModelGenericBotTriggerEvent) {
                erLhcoreClassGenericBotWorkflow::processTrigger($params['chat'],  $event->trigger, true, array());
            } else {
                $event = erLhcoreClassGenericBotWorkflow::findTextMatchingEvent('response_not_found', $chatVariables['gbot_id']);
                erLhcoreClassGenericBotWorkflow::processTrigger($params['chat'],  $event->trigger, true, array());
            }
        }

        return array('status' => erLhcoreClassChatEventDispatcher::STOP_WORKFLOW, 'event' => null);
    }

    public function autoload($className)
    {
        $classesArray = array(
            'erLhcoreClassLhcaibotWorker'   => 'extension/lhcaibot/classes/lhqueuelhcaibotworker.php'
        );

        if (key_exists($className, $classesArray)) {
            include_once $classesArray[$className];
        }
    }

    // Just sample method
    public function executeRequest($data, $path)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->settings['ai_settings']['host'] . $path);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Live Helper Chat AI client v1.0');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result,true);

        if (isset($response['error']) && $this->settings['app_settings']['enable_debug'] == true) {
            erLhcoreClassLog::write($result .' - '. json_encode($data));
        }
    }


    public function registerAutoload() {
        spl_autoload_register ( array (
            $this,
            'autoload'
        ), true, false );
    }

    public function __get($var) {
        switch ($var) {

            case 'settings' :
                $this->settings = include ('extension/lhcaibot/settings/settings.ini.php');
                return $this->settings;
                break;

            default :
                ;
                break;
        }
    }
}


