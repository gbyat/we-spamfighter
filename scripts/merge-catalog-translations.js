#!/usr/bin/env node
/**
 * One-off helper: merge known German translations into locale catalog JSON.
 * Add new entries here when strings are introduced, then run i18n.
 */

const path = require('path');
const { readLf, writeLf } = require('./process');

const catalogPath = path.join(__dirname, 'translations', 'de_DE.json');
const catalog = JSON.parse(readLf(catalogPath));

/** @type {Record<string, string>} */
const additions = {
	'https://github.com/gbyat/we-spamfighter': 'https://github.com/gbyat/we-spamfighter',
	'Advanced spam protection for Contact Form 7 and Comments using AI-powered and heuristic detection. Works with or without OpenAI - includes local spam detection for cost-effective filtering.':
		'Erweiterter Spam-Schutz für Contact Form 7 und Kommentare mit KI-gestützter und heuristischer Erkennung. Funktioniert mit oder ohne OpenAI – enthält lokale Spam-Erkennung für kosteneffektive Filterung.',
	'webentwicklerin, Gabriele Laesser': 'webentwicklerin, Gabriele Laesser',
	'AI Detection': 'KI-Erkennung',
	'Enable AI Detection': 'KI-Erkennung aktivieren',
	'Use AI to detect spam (via WordPress Connectors or direct OpenAI API)':
		'Spam mit KI erkennen (über WordPress Connectors oder direkte OpenAI-API)',
	'AI Connection': 'KI-Verbindung',
	'Use credentials from Settings → Connectors (WordPress 7.0+) or the plugin’s own OpenAI API key.':
		'Zugangsdaten aus Einstellungen → Connectors (WordPress 7.0+) oder den eigenen OpenAI-API-Schlüssel des Plugins verwenden.',
	'AI Provider (Connector)': 'KI-Anbieter (Connector)',
	'Choose which configured WordPress AI connector to use (e.g. Mistral, OpenAI, Anthropic).':
		'Wählen Sie den konfigurierten WordPress-AI-Connector (z. B. Mistral, OpenAI, Anthropic).',
	'Preferred Model(s)': 'Bevorzugte(s) Modell(e)',
	'Optional. Comma-separated model IDs for the selected provider (first available match is used). Leave empty to use the provider default.':
		'Optional. Kommagetrennte Modell-IDs für den gewählten Anbieter (das erste verfügbare Modell wird verwendet). Leer lassen für die Anbieter-Standardeinstellung.',
	'Only for “Direct OpenAI API”. Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>. You can also define WE_SPAMFIGHTER_OPENAI_KEY in wp-config.php.':
		'Nur für „Direct OpenAI API“. API-Schlüssel auf der <a href="%s" target="_blank">OpenAI Platform</a> erstellen. Alternativ WE_SPAMFIGHTER_OPENAI_KEY in wp-config.php definieren.',
	'Only for “Direct OpenAI API”. Which model to use for spam detection.':
		'Nur für „Direct OpenAI API“. Welches Modell für die Spam-Erkennung verwendet wird.',
	'Enable field-type pattern analysis': 'Feldtyp-Musteranalyse aktivieren',
	'Analyze submissions by field role (single-line text vs message vs email vs URL). Catches URLs in name fields, spam in message bodies, disposable emails, and more.':
		'Übermittlungen nach Feldrolle prüfen (Einzeilentext vs. Nachricht vs. E-Mail vs. URL). Erkennt URLs in Namensfeldern, Spam in Nachrichtentexten, Wegwerf-E-Mails und mehr.',
	'Enable duplicate message detection': 'Erkennung doppelter Nachrichten aktivieren',
	'Flag identical messages resubmitted by the same email or IP within 24 hours.':
		'Identische Nachrichten markieren, die innerhalb von 24 Stunden von derselben E-Mail oder IP erneut gesendet werden.',
	'Enable business terminology signals': 'B2B-/Fachbegriff-Signale aktivieren',
	'Soft score boost when message text uses typical B2B/legal vocabulary (reduces false positives for legitimate inquiries).':
		'Leichte Score-Erhöhung, wenn der Nachrichtentext typische B2B-/Rechtsvokabeln enthält (reduziert Fehlalarme bei legitimen Anfragen).',
	'Enable strict email/URL field format checks': 'Strenge E-Mail-/URL-Feldformat-Prüfungen aktivieren',
	'Validate dedicated email and URL fields (invalid format, email in URL field, etc.). Off by default to avoid blocking edge cases.':
		'Dedizierte E-Mail- und URL-Felder validieren (ungültiges Format, E-Mail im URL-Feld usw.). Standardmäßig aus, um Grenzfälle nicht zu blockieren.',
	'Configure AI spam detection. You can use WordPress Connectors (recommended on WordPress 7.0+) or a direct OpenAI API key in this plugin.':
		'KI-Spam-Erkennung konfigurieren. WordPress Connectors (empfohlen ab WordPress 7.0+) oder einen direkten OpenAI-API-Schlüssel in diesem Plugin verwenden.',
	'Configure AI spam detection using a direct OpenAI API key. WordPress Connectors require WordPress 7.0 or newer.':
		'KI-Spam-Erkennung mit direktem OpenAI-API-Schlüssel konfigurieren. WordPress Connectors erfordern WordPress 7.0 oder neuer.',
	'Direct OpenAI API (plugin API key)': 'Direct OpenAI API (Plugin-API-Schlüssel)',
	'WordPress Connectors (Settings → Connectors)': 'WordPress Connectors (Einstellungen → Connectors)',
	'— Select provider —': '— Anbieter wählen —',
	'The previously selected provider is no longer connected. Choose a connected provider or configure one under Settings → Connectors.':
		'Der zuvor gewählte Anbieter ist nicht mehr verbunden. Wählen Sie einen verbundenen Anbieter oder konfigurieren Sie einen unter Einstellungen → Connectors.',
	'No connected AI connectors found. Configure an AI provider under Settings → Connectors.':
		'Keine verbundenen KI-Connectors gefunden. Konfigurieren Sie einen KI-Anbieter unter Einstellungen → Connectors.',
	'Manage connectors in WordPress': 'Connectors in WordPress verwalten',
	'Test AI Connection': 'KI-Verbindung testen',
	'Test your configured AI connection (WordPress Connectors or direct OpenAI API).':
		'Die konfigurierte KI-Verbindung testen (WordPress Connectors oder direkte OpenAI-API).',
	'Please enable AI detection and select a configured WordPress connector.':
		'Bitte KI-Erkennung aktivieren und einen konfigurierten WordPress-Connector wählen.',
	'Please enable AI detection and configure your OpenAI API key first.':
		'Bitte zuerst KI-Erkennung aktivieren und Ihren OpenAI-API-Schlüssel konfigurieren.',
	'AI connection test failed: %s': 'KI-Verbindungstest fehlgeschlagen: %s',
	'Unknown error': 'Unbekannter Fehler',
	'WordPress Connectors (%s)': 'WordPress Connectors (%s)',
	'Direct OpenAI API': 'Direct OpenAI API',
	'%1$s connection successful! Test spam score: %2$.2f':
		'%1$s-Verbindung erfolgreich! Test-Spam-Score: %2$.2f',
};

let merged = 0;
Object.entries(additions).forEach(([msgid, msgstr]) => {
	if (!Object.prototype.hasOwnProperty.call(catalog, msgid)) {
		console.warn(`Catalog key not found (run export-po-catalog first?): ${msgid.slice(0, 60)}…`);
		return;
	}
	catalog[msgid] = msgstr;
	merged += 1;
});

writeLf(catalogPath, `${JSON.stringify(catalog, null, 2)}\n`);
console.log(`Merged ${merged} translation(s) into ${catalogPath}`);
