<?php
/**
 * Chatbot mount-point template.
 *
 * The full widget UI is built client-side (public/assets/js/chatbot.js)
 * from the sanitized config in PartinoChatbotData; this template only
 * outputs the root node so themes/plugins can override or relocate it.
 *
 * @package Partino\Chatbot
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="partino-chatbot" data-partino-chatbot="1" aria-live="polite"></div>
