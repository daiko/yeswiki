<?php

use YesWiki\Core\YesWikiHandler;
use YesWiki\Bazar\Service\EntryManager;

class HtmlToWikiHandler extends YesWikiHandler
{
    protected $entryManager;

    public function run()
    {
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        // user is admin ?
        if (!$this->wiki->UserIsAdmin()) {
            // not connected
            return $this->twig->renderInSquelette('@templates/alert-message-with-back.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS') . ' (htmltowiki)'
            ]);
        }

        if (
            $this->wiki->page
            && null !== $entry = $this->entryManager->getOne($this->wiki->page['tag'])
        ) {
            $content = $entry['bf_contenu'];

            dump($content);

            $content = preg_replace(
                [
                    '/""(\s*)""/U',
                    '/(\n\r){3,}/',
                ], [
                    '$1',
                    "\n\r\n\r",
                ],
                $content
            );

            dump($content);

            return;
        }

        return "Handler HTMLtoWiki only runs on entries";
    }
}