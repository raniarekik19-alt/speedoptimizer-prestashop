<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class SpeedOptimizer extends Module
{

    public function __construct()
    {
        $this->name = 'speedoptimizer';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Rania';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = 'Speed Optimizer';
        $this->description = 'Module PrestaShop pour optimiser la vitesse de la boutique.';
    }



    /**
     * Installation
     */
    public function install()
    {
        return parent::install();
    }



    /**
     * Désinstallation
     */
    public function uninstall()
    {
        return parent::uninstall();
    }





    /**
     * Gestion des actions
     */
    public function postProcess()
    {

        /*
        ============================
        ENREGISTREMENT DE LA CONFIGURATION
        ============================
        */

        if (Tools::isSubmit('saveSpeedOptimizer')) {

            $apiKey = trim(Tools::getValue('PAGESPEED_API_KEY'));
            $claudeKey = trim(Tools::getValue('CLAUDE_API_KEY'));

            Configuration::updateValue(
                'SPEEDOPTIMIZER_PAGESPEED_API_KEY',
                $apiKey
            );

            Configuration::updateValue(
                'SPEEDOPTIMIZER_CLAUDE_API_KEY',
                $claudeKey
            );

            $this->context->controller->confirmations[] =
                'Configuration enregistrée avec succès.';

        }


        /*
        ============================
        ANALYSE GOOGLE PAGESPEED
        ============================
        */

        if (Tools::isSubmit('analyzeSpeed')) {

            $result = $this->analyzePageSpeed();

            if (isset($result['error'])) {

                $this->context->controller->errors[] =
                    $result['error'];

            } else {

                $score = $result['lighthouseResult']['categories']['performance']['score'] ?? null;

                if ($score !== null) {

                    $score = round($score * 100);

                    $this->context->controller->confirmations[] =
                        'Score Performance Google PageSpeed : ' . $score . '/100';

                } else {

                    $this->context->controller->errors[] =
                        'Impossible de récupérer le score Performance.';
                }
            }
        }




        /*
        ============================
        RECOMMANDATIONS IA (GPSI -> CLAUDE)
        ============================
        */

        if (Tools::isSubmit('getAiRecommendations')) {

            $pagespeedResult = $this->analyzePageSpeed();

            if (isset($pagespeedResult['error'])) {

                $this->context->controller->errors[] = $pagespeedResult['error'];

            } else {

                $issues = $this->extractPageSpeedIssues($pagespeedResult);

                $recommendations = $this->getClaudeRecommendations($issues);

                if (isset($recommendations['error'])) {

                    $this->context->controller->errors[] = $recommendations['error'];

                } else {

                    // Stockage temporaire des recommandations en attente
                    // de validation par l'utilisateur (pas d'action appliquée
                    // automatiquement à ce stade).
                    Configuration::updateValue(
                        'SPEEDOPTIMIZER_AI_RECOMMENDATIONS',
                        json_encode($recommendations)
                    );

                    $this->context->controller->confirmations[] =
                        count($recommendations) . ' recommandation(s) générée(s). '
                        . 'Sélectionnez celles à appliquer ci-dessous.';

                }

            }

        }




        /*
        ============================
        APPLICATION DES RECOMMANDATIONS CHOISIES
        ============================
        */

        if (Tools::isSubmit('applyAiRecommendations')) {

            $selected = Tools::getValue('apply_reco');

            if (empty($selected) || !is_array($selected)) {

                $this->context->controller->errors[] =
                    'Aucune recommandation sélectionnée.';

            } else {

                $applied = $this->applySelectedRecommendations($selected);

                $this->context->controller->confirmations[] =
                    implode(' ', $applied);

                // On vide les recommandations une fois traitées.
                Configuration::deleteByName('SPEEDOPTIMIZER_AI_RECOMMENDATIONS');

            }

        }




        /*
        ============================
        ACTIVATION OPTIMISATIONS
        ============================
        */

        if (Tools::isSubmit('enableOptimizations')) {


            // Cache Smarty
            Configuration::updateValue(
                'PS_SMARTY_CACHE',
                1
            );


            // Désactiver compilation forcée
            Configuration::updateValue(
                'PS_SMARTY_FORCE_COMPILE',
                0
            );



            // Compression CSS
            Configuration::updateValue(
                'PS_CSS_THEME_CACHE',
                1
            );



            // Compression JS
            Configuration::updateValue(
                'PS_JS_THEME_CACHE',
                1
            );



            // Compression HTML
            Configuration::updateValue(
                'PS_HTML_THEME_COMPRESSION',
                1
            );



            // Compression JS inline
            Configuration::updateValue(
                'PS_JS_HTML_THEME_COMPRESSION',
                1
            );



            $this->context->controller->confirmations[] =
            'Optimisations recommandées activées avec succès.';


        }





        /*
        ============================
        CONVERSION IMAGES EN WEBP
        ============================
        */

        if (Tools::isSubmit('convertToWebp')) {

            $stats = $this->convertImagesToWebp();

            if (isset($stats['message'])) {

                $this->context->controller->errors[] = $stats['message'];

            } else {

                $this->context->controller->confirmations[] =
                sprintf(
                    '%d images converties, %d ignorées (déjà à jour), %d erreurs.',
                    $stats['converted'],
                    $stats['skipped'],
                    $stats['errors']
                );

            }

        }





        /*
        ============================
        CACHE NAVIGATEUR
        ============================
        */

        if (Tools::isSubmit('enableBrowserCache')) {

            $result = $this->enableBrowserCache();

            if ($result) {
                $this->context->controller->confirmations[] =
                'Cache navigateur activé avec succès.';
            } else {
                $this->context->controller->errors[] =
                'Impossible de modifier le fichier .htaccess (vérifiez les droits d\'écriture).';
            }

        }


        if (Tools::isSubmit('disableBrowserCache')) {

            $result = $this->disableBrowserCache();

            if ($result) {
                $this->context->controller->confirmations[] =
                'Cache navigateur désactivé.';
            } else {
                $this->context->controller->errors[] =
                'Impossible de modifier le fichier .htaccess.';
            }

        }





        /*
        ============================
        OPTIMISATION BASE DE DONNEES
        ============================
        */

        if (Tools::isSubmit('optimizeDatabase')) {

            $stats = $this->optimizeDatabase();

            if (isset($stats['error'])) {

                $this->context->controller->errors[] =
                $stats['error'];

            } else {

                $this->context->controller->confirmations[] =
                sprintf(
                    'Base de données optimisée : %d lignes de logs supprimées, %d tables optimisées.',
                    $stats['rows_deleted'],
                    $stats['tables_optimized']
                );

            }

        }





        /*
        ============================
        DETECTION MODULES INUTILES
        ============================
        */

        if (Tools::isSubmit('scanUnusedModules')) {

            // Le résultat est juste affiché dans le formulaire,
            // pas besoin d'action supplémentaire ici.
            // displayForm() rappelle getUnusedModules() pour l'affichage.

        }


    }







    /**
     * Appel API Google PageSpeed avec cache pour éviter les appels répétés.
     */
    public function analyzePageSpeed()
    {
        // URL récupérée dynamiquement (plus de valeur en dur) : fonctionne
        // sur n'importe quelle boutique PrestaShop, pas seulement en local.
         $url = Context::getContext()->shop->getBaseURL(true);
        
        //$url = 'https://smcollection.tn';
        // Cache du résultat PageSpeed pendant 1 heure.
        $cacheKey = 'SPEEDOPTIMIZER_PAGESPEED_CACHE';
        $cacheDuration = 3600;

        $cached = Configuration::get($cacheKey);

        if ($cached) {
            $cachedData = json_decode($cached, true);

            if (
                is_array($cachedData)
                && isset($cachedData['timestamp'], $cachedData['result'])
                && (time() - (int) $cachedData['timestamp']) < $cacheDuration
            ) {
                return $cachedData['result'];
            }
        }

        $apiKey = Configuration::get('SPEEDOPTIMIZER_PAGESPEED_API_KEY');

        $apiUrl =
            'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
            . '?url=' . urlencode($url)
            . '&strategy=mobile'
            . '&category=performance';

        // Ajoute la clé API si elle est configurée : augmente fortement
        // le quota quotidien par rapport aux appels sans clé.
        if (!empty($apiKey)) {
            $apiUrl .= '&key=' . urlencode($apiKey);
        }

        $response = Tools::file_get_contents($apiUrl);

        if (!$response) {
            return [
                'error' => 'Impossible de contacter Google PageSpeed Insights. Vérifiez la connexion internet du serveur.'
            ];
        }

        $result = json_decode($response, true);

        if (!is_array($result)) {
            return [
                'error' => 'Réponse invalide de Google PageSpeed Insights.'
            ];
        }

        // Gestion propre du quota Google sans afficher l'erreur technique complète.
        if (isset($result['error'])) {
            $message = $result['error']['message'] ?? '';

            if (
                stripos($message, 'Quota exceeded') !== false
                || stripos($message, 'Queries per day') !== false
            ) {
                // Réutiliser réellement le dernier résultat en cache, même expiré,
                // plutôt que de se contenter d'une simple erreur.
                if ($cached) {
                    $cachedData = json_decode($cached, true);
                    if (is_array($cachedData) && isset($cachedData['result'])) {
                        return $cachedData['result'];
                    }
                }

                return [
                    'error' => 'Le quota quotidien de Google PageSpeed Insights est atteint, '
                        . 'et aucun résultat précédent n\'est disponible en cache.'
                ];
            }

            return [
                'error' => 'Google PageSpeed Insights est temporairement indisponible. '
                    . 'Veuillez réessayer plus tard.'
            ];
        }

        if (!isset($result['lighthouseResult']['categories']['performance']['score'])) {
            return [
                'error' => 'Impossible de récupérer le score de performance.'
            ];
        }

        // Sauvegarder le résultat pour éviter de rappeler l'API à chaque clic.
        Configuration::updateValue(
            $cacheKey,
            json_encode([
                'timestamp' => time(),
                'result' => $result
            ])
        );

        return $result;
    }





    /**
     * Liste fermée des actions que le module sait réellement exécuter.
     * Claude doit choisir uniquement parmi ces clés — jamais en inventer
     * de nouvelles — puisque ce sont les seules actions que le module
     * peut appliquer techniquement.
     */
    public function getAllowedActions()
    {
        return [
            'enable_optimizations' => 'Activer le cache Smarty et la compression CSS/JS/HTML',
            'convert_webp'         => 'Convertir les images du catalogue en WebP',
            'enable_browser_cache' => 'Activer le cache navigateur (règles .htaccess)',
            'optimize_database'    => 'Optimiser la base de données (logs + OPTIMIZE TABLE)',
            'review_unused_modules' => 'Vérifier les modules désactivés (information seulement, aucune suppression automatique)',
        ];
    }





    /**
     * Extrait du résultat brut PageSpeed les audits en échec (score < 1),
     * sous une forme courte et lisible, pour les envoyer à Claude sans
     * surcharger le prompt avec tout le JSON Lighthouse.
     */
    public function extractPageSpeedIssues($pagespeedResult)
    {
        $issues = [];

        $audits = $pagespeedResult['lighthouseResult']['audits'] ?? [];

        foreach ($audits as $auditId => $audit) {

            if (!isset($audit['score'])) {
                continue;
            }

            // On ne garde que les audits ratés ou partiellement ratés.
            if ($audit['score'] === null || $audit['score'] >= 1) {
                continue;
            }

            $issues[] = [
                'id' => $auditId,
                'title' => $audit['title'] ?? $auditId,
                'description' => $audit['displayValue'] ?? '',
            ];

        }

        return $issues;
    }





    /**
     * Envoie les problèmes détectés par PageSpeed Insights à l'API Claude
     * et demande une sélection de recommandations parmi les actions que
     * le module sait réellement appliquer (voir getAllowedActions()).
     * Retourne un tableau de recommandations : ['action' => clé, 'reason' => texte]
     */
    public function getClaudeRecommendations($issues)
    {
        $claudeApiKey = Configuration::get('SPEEDOPTIMIZER_CLAUDE_API_KEY');

        if (empty($claudeApiKey)) {
            return [
                'error' => 'Aucune clé API Claude configurée. Renseignez-la dans le champ ci-dessous puis Enregistrer.'
            ];
        }

        if (empty($issues)) {
            return [];
        }

        $allowedActions = $this->getAllowedActions();

        $issuesText = '';
        foreach ($issues as $issue) {
            $issuesText .= '- ' . $issue['title'];
            if (!empty($issue['description'])) {
                $issuesText .= ' (' . $issue['description'] . ')';
            }
            $issuesText .= "\n";
        }

        $actionsText = '';
        foreach ($allowedActions as $key => $label) {
            $actionsText .= '- ' . $key . ' : ' . $label . "\n";
        }

        $prompt = "Voici les problèmes de performance détectés par Google PageSpeed Insights sur une boutique PrestaShop :\n\n"
            . $issuesText
            . "\nVoici la LISTE FERMÉE des actions que le module peut réellement exécuter (n'en propose aucune autre) :\n\n"
            . $actionsText
            . "\nParmi cette liste fermée uniquement, choisis les actions pertinentes pour corriger les problèmes ci-dessus. "
            . "Réponds UNIQUEMENT avec un tableau JSON valide, sans texte autour, sans balises markdown, "
            . "de la forme : [{\"action\": \"clé_exacte\", \"reason\": \"explication courte en français pour l'utilisateur\"}]. "
            . "N'inclus une action que si elle est vraiment justifiée par un des problèmes listés.";

        $response = $this->callClaudeApi($claudeApiKey, $prompt);

        if (isset($response['error'])) {
            return $response;
        }

        $decoded = json_decode($response['text'], true);

        if (!is_array($decoded)) {
            return [
                'error' => 'La réponse de Claude n\'a pas pu être interprétée comme du JSON valide.'
            ];
        }

        // Filtre de sécurité : on ne garde que les actions qui existent
        // réellement dans notre liste fermée, au cas où le modèle en
        // proposerait une en dehors malgré la consigne.
        $filtered = [];
        foreach ($decoded as $reco) {
            if (isset($reco['action']) && isset($allowedActions[$reco['action']])) {
                $filtered[] = [
                    'action' => $reco['action'],
                    'label' => $allowedActions[$reco['action']],
                    'reason' => $reco['reason'] ?? '',
                ];
            }
        }

        return $filtered;
    }





    /**
     * Appel brut à l'API Anthropic (Claude). Retourne ['text' => ...]
     * ou ['error' => ...].
     */
/**
 * Appel à Claude via OpenRouter.
 * Retourne ['text' => ...] ou ['error' => ...].
 */
private function callClaudeApi($apiKey, $prompt)
{
    $payload = json_encode([
        'model' => 'anthropic/claude-sonnet-4',
        'max_tokens' => 1024,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ]);

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        //'HTTP-Referer: https://smcollection.tn',
        'X-Title: Speed Optimizer PrestaShop'
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    // Erreur réseau cURL
    if ($response === false) {
        return [
            'error' => 'Impossible de contacter OpenRouter : ' . $curlError
        ];
    }

    // Décodage JSON
    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        return [
            'error' => 'Réponse invalide reçue depuis OpenRouter.'
        ];
    }

    // Erreur API
    if ($httpCode < 200 || $httpCode >= 300) {

        $message = $decoded['error']['message']
            ?? 'Erreur inconnue de l\'API OpenRouter.';

        return [
            'error' => 'Erreur API Claude via OpenRouter (HTTP '
                . $httpCode . ') : ' . $message
        ];
    }

    // Récupération du texte généré
    $text = $decoded['choices'][0]['message']['content'] ?? null;

    if ($text === null) {
        return [
            'error' => 'Réponse inattendue de OpenRouter.'
        ];
    }

    return [
        'text' => $text
    ];
}





    /**
     * Convertit les images du dossier img/ du shop en WebP.
     */
    public function convertImagesToWebp()
    {
        require_once _PS_MODULE_DIR_ . 'speedoptimizer/classes/WebpConverter.php';

        $converter = new WebpConverter();

        // Conversion limitée au dossier img/ du shop (img produits,
        // catégories, marques, etc.) via la constante native PrestaShop,
        // plutôt que le dossier assets/ du thème.
        return $converter->convertAll(_PS_IMG_DIR_);
    }





    /**
     * Applique les recommandations cochées par l'utilisateur en appelant
     * la méthode correspondante à chaque clé d'action. Retourne un
     * tableau de messages lisibles à afficher.
     */
    public function applySelectedRecommendations($selectedActions)
    {
        $messages = [];

        foreach ($selectedActions as $action) {

            switch ($action) {

                case 'enable_optimizations':
                    Configuration::updateValue('PS_SMARTY_CACHE', 1);
                    Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', 0);
                    Configuration::updateValue('PS_CSS_THEME_CACHE', 1);
                    Configuration::updateValue('PS_JS_THEME_CACHE', 1);
                    Configuration::updateValue('PS_HTML_THEME_COMPRESSION', 1);
                    Configuration::updateValue('PS_JS_HTML_THEME_COMPRESSION', 1);
                    $messages[] = 'Optimisations (cache/compression) activées.';
                    break;

                case 'convert_webp':
                    $stats = $this->convertImagesToWebp();
                    if (isset($stats['message'])) {
                        $messages[] = 'WebP : ' . $stats['message'];
                    } else {
                        $messages[] = sprintf(
                            'WebP : %d converties, %d ignorées, %d erreurs.',
                            $stats['converted'],
                            $stats['skipped'],
                            $stats['errors']
                        );
                    }
                    break;

                case 'enable_browser_cache':
                    $ok = $this->enableBrowserCache();
                    $messages[] = $ok
                        ? 'Cache navigateur activé.'
                        : 'Échec activation cache navigateur (droits fichier .htaccess).';
                    break;

                case 'optimize_database':
                    $stats = $this->optimizeDatabase();
                    if (isset($stats['error'])) {
                        $messages[] = 'Base de données : ' . $stats['error'];
                    } else {
                        $messages[] = sprintf(
                            'Base de données : %d lignes supprimées, %d tables optimisées.',
                            $stats['rows_deleted'],
                            $stats['tables_optimized']
                        );
                    }
                    break;

                case 'review_unused_modules':
                    $unused = $this->getUnusedModules();
                    $messages[] = count($unused) . ' module(s) désactivé(s) à vérifier manuellement.';
                    break;

            }

        }

        return $messages;
    }





    /**
     * Active le cache navigateur en ajoutant les règles au .htaccess
     */
    public function enableBrowserCache()
    {
        $htaccessPath = _PS_ROOT_DIR_ . '/.htaccess';

        if (!is_writable($htaccessPath)) {
            return false;
        }

        $currentContent = file_get_contents($htaccessPath);

        // Évite les doublons si déjà activé
        if (strpos($currentContent, '# BEGIN SpeedOptimizer Cache') !== false) {
            return true;
        }

        $cacheRules = "\n# BEGIN SpeedOptimizer Cache\n"
            . "<IfModule mod_expires.c>\n"
            . "    ExpiresActive On\n"
            . "    ExpiresByType image/jpg \"access plus 1 year\"\n"
            . "    ExpiresByType image/jpeg \"access plus 1 year\"\n"
            . "    ExpiresByType image/gif \"access plus 1 year\"\n"
            . "    ExpiresByType image/png \"access plus 1 year\"\n"
            . "    ExpiresByType image/webp \"access plus 1 year\"\n"
            . "    ExpiresByType image/svg+xml \"access plus 1 year\"\n"
            . "    ExpiresByType text/css \"access plus 1 month\"\n"
            . "    ExpiresByType application/javascript \"access plus 1 month\"\n"
            . "    ExpiresByType text/javascript \"access plus 1 month\"\n"
            . "    ExpiresByType image/x-icon \"access plus 1 year\"\n"
            . "</IfModule>\n"
            . "<IfModule mod_headers.c>\n"
            . "    <FilesMatch \"\\.(ico|jpg|jpeg|png|gif|webp|svg|css|js)$\">\n"
            . "        Header set Cache-Control \"public, max-age=31536000\"\n"
            . "    </FilesMatch>\n"
            . "</IfModule>\n"
            . "# END SpeedOptimizer Cache\n";

        return (bool) file_put_contents($htaccessPath, $cacheRules, FILE_APPEND);
    }





    /**
     * Désactive le cache navigateur en retirant les règles du .htaccess
     */
    public function disableBrowserCache()
    {
        $htaccessPath = _PS_ROOT_DIR_ . '/.htaccess';

        if (!is_writable($htaccessPath)) {
            return false;
        }

        $content = file_get_contents($htaccessPath);
        $pattern = '/\n# BEGIN SpeedOptimizer Cache.*?# END SpeedOptimizer Cache\n/s';
        $newContent = preg_replace($pattern, '', $content);

        return (bool) file_put_contents($htaccessPath, $newContent);
    }





    /**
     * Optimise la base de données :
     * - vide les tables de logs/statistiques volumineuses
     * - lance OPTIMIZE TABLE sur les tables du shop
     */
    public function optimizeDatabase()
    {

        $rowsDeleted = 0;
        $tablesOptimized = 0;

        $prefix = _DB_PREFIX_;

        // Tables de logs / statistiques qu'on peut vider sans risque
        $logTables = [
            'connections',
            'connections_page',
            'connections_source',
            'guest',
            'statssearch',
        ];

        foreach ($logTables as $table) {

            $fullTable = $prefix . $table;

            // On vérifie que la table existe avant de la vider
            $exists = Db::getInstance()->executeS(
                "SHOW TABLES LIKE '" . pSQL($fullTable) . "'"
            );

            if (!$exists) {
                continue;
            }

            $countBefore = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . $fullTable . '`'
            );

            $success = Db::getInstance()->execute(
                'TRUNCATE TABLE `' . $fullTable . '`'
            );

            if ($success) {
                $rowsDeleted += $countBefore;
            }

        }

        // Récupère toutes les tables de la boutique pour les optimiser
        $allTables = Db::getInstance()->executeS(
            "SHOW TABLES LIKE '" . pSQL($prefix) . "%'"
        );

        if (!$allTables) {

            return [
                'error' => 'Impossible de récupérer la liste des tables.'
            ];

        }

        foreach ($allTables as $row) {

            $tableName = array_values($row)[0];

            $success = Db::getInstance()->execute(
                'OPTIMIZE TABLE `' . $tableName . '`'
            );

            if ($success) {
                $tablesOptimized++;
            }

        }

        return [
            'rows_deleted' => $rowsDeleted,
            'tables_optimized' => $tablesOptimized,
        ];

    }





    /**
     * Récupère la liste des modules installés mais désactivés.
     * On ne les désinstalle JAMAIS automatiquement : trop risqué
     * (certains modules "inactifs" sont utilisés par le thème ou
     * par d'autres modules). On se contente de lister, la décision
     * de désinstaller reste manuelle.
     */
    public function getUnusedModules()
    {

        $modules = Module::getModulesInstalled();

        $unused = [];

        foreach ($modules as $moduleRow) {

            if ((int) $moduleRow['active'] === 0) {

                $unused[] = [
                    'name' => $moduleRow['name'],
                ];

            }

        }

        return $unused;

    }







    /**
     * Configuration du module
     */
    public function getContent()
    {

        $this->postProcess();


        return $this->displayForm();

    }








    /**
     * Interface Back Office
     */
    public function displayForm()
    {


        $html = '';



        $html .= '

        <div class="panel">

            <h3>
                <i class="icon-rocket"></i>
                Speed Optimizer
            </h3>


            <p>
                Analyse et optimisation des performances
                de votre boutique PrestaShop.
            </p>



            <form method="post">

                <div class="form-group" style="margin-bottom:15px;">
                    <label for="PAGESPEED_API_KEY">
                        Clé API Google PageSpeed Insights (optionnel)
                    </label>
                    <input
                        type="text"
                        id="PAGESPEED_API_KEY"
                        name="PAGESPEED_API_KEY"
                        class="form-control"
                        style="max-width:500px;"
                        value="' . htmlspecialchars(Configuration::get('SPEEDOPTIMIZER_PAGESPEED_API_KEY')) . '"
                        placeholder="Laisser vide pour utiliser le quota gratuit limité"
                    />
                    <p class="help-block">
                        Sans clé, Google limite fortement le nombre d\'analyses par jour.
                        Une clé API gratuite (Google Cloud Console) augmente ce quota.
                    </p>
                </div>



                <div class="form-group" style="margin-bottom:15px;">
                    <label for="CLAUDE_API_KEY">
                        Clé API OpenRouter — pour les recommandations IA Claude
                    </label>
                    <input
                        type="text"
                        id="CLAUDE_API_KEY"
                        name="CLAUDE_API_KEY"
                        class="form-control"
                        style="max-width:500px;"
                        value="' . htmlspecialchars(Configuration::get('SPEEDOPTIMIZER_CLAUDE_API_KEY')) . '"
                        placeholder="sk-ant-..."
                    />
                    <p class="help-block">
                        Nécessaire pour générer des recommandations personnalisées
                        à partir du diagnostic PageSpeed Insights.
                    </p>
                </div>



                <button
                type="submit"
                name="saveSpeedOptimizer"
                class="btn btn-primary">

                Enregistrer

                </button>





                <button
                type="submit"
                name="analyzeSpeed"
                class="btn btn-info"
                style="margin-left:10px;">

                Analyser

                </button>




                <button
                type="submit"
                name="getAiRecommendations"
                class="btn btn-primary"
                style="margin-left:10px;">

                Obtenir des recommandations IA

                </button>






                <button
                type="submit"
                name="enableOptimizations"
                class="btn btn-warning"
                style="margin-left:10px;">

                Activer les optimisations recommandées

                </button>





                <button
                type="submit"
                name="convertToWebp"
                class="btn btn-success"
                style="margin-left:10px;">

                Convertir les images en WebP

                </button>





                <button
                type="submit"
                name="enableBrowserCache"
                class="btn btn-success"
                style="margin-left:10px;">

                Activer le cache navigateur

                </button>





                <button
                type="submit"
                name="optimizeDatabase"
                class="btn btn-danger"
                style="margin-left:10px;">

                Optimiser la base de données

                </button>





                <button
                type="submit"
                name="scanUnusedModules"
                class="btn btn-default"
                style="margin-left:10px;">

                Vérifier les modules inutiles

                </button>



            </form>



        ';



        // Affichage du résultat du scan, si demandé
        if (Tools::isSubmit('scanUnusedModules')) {

            $unusedModules = $this->getUnusedModules();

            if (empty($unusedModules)) {

                $html .= '<div class="alert alert-success" style="margin-top:15px;">
                    Aucun module désactivé trouvé, rien à nettoyer.
                </div>';

            } else {

                $html .= '<div class="alert alert-warning" style="margin-top:15px;">
                    <strong>' . count($unusedModules) . ' module(s) désactivé(s) trouvé(s) :</strong>
                    <ul style="margin-top:10px;">';

                foreach ($unusedModules as $mod) {

                    $html .= '<li>' . htmlspecialchars($mod['name']) . '</li>';

                }

                $html .= '</ul>
                    <p style="margin-top:10px;">
                        Ces modules sont installés mais désactivés. Vérifiez qu\'ils ne sont
                        pas utilisés par votre thème avant de les désinstaller manuellement
                        depuis le Gestionnaire de modules.
                    </p>
                </div>';

            }

        }



        // Affichage des recommandations IA en attente de validation
        $storedRecommendations = Configuration::get('SPEEDOPTIMIZER_AI_RECOMMENDATIONS');

        if (!empty($storedRecommendations)) {

            $recommendations = json_decode($storedRecommendations, true);

            if (is_array($recommendations) && !empty($recommendations)) {

                $html .= '<div class="alert alert-info" style="margin-top:15px;">
                    <strong>Recommandations générées par Claude :</strong>
                    <form method="post" style="margin-top:10px;">';

                foreach ($recommendations as $index => $reco) {

                    $checkboxId = 'reco_' . $index;

                    $html .= '<div class="checkbox" style="margin-bottom:10px;">
                        <label for="' . $checkboxId . '">
                            <input
                                type="checkbox"
                                id="' . $checkboxId . '"
                                name="apply_reco[]"
                                value="' . htmlspecialchars($reco['action']) . '"
                            />
                            <strong>' . htmlspecialchars($reco['label']) . '</strong><br/>
                            <span style="color:#666;">' . htmlspecialchars($reco['reason']) . '</span>
                        </label>
                    </div>';

                }

                $html .= '<button
                        type="submit"
                        name="applyAiRecommendations"
                        class="btn btn-success">

                        Appliquer les recommandations sélectionnées

                        </button>
                    </form>
                </div>';

            }

        }



        $html .= '

        </div>

        ';



        return $html;


    }


}