<?php

namespace app\modules\admin\controllers;



use app\models\Material;
use app\models\Participant;

class EmailController
{
    private $inboxData;

    private $searchKey;

    private $emailReadingLimit;

    /**
     * EmailController constructor.
     */
    public function __construct()
    {
        $this->inboxData = unserialize(\Yii::$app->config->get('INBOX_DATA'));

        $this->searchKey = \Yii::$app->config->get('HEADER_FOR_EMAIL_SEARCH');

        $this->emailReadingLimit = \Yii::$app->config->get('EMAIL_READING_LIMIT');

    }


    /**
     * @return bool|resource
     */
    private function getInbox()
    {
        try {
            $inbox = imap_open($this->inboxData['hostname'], $this->inboxData['username'], $this->inboxData['password']) or die('Cannot connect to Gmail: ' . imap_last_error());
        } catch (\Exception $exception) {
            \Yii::$app->getSession()->setFlash('error', "Не вдалося встановити з'єднання з срвером вхідної пошти (IMAP)");
            return false;
        }

        return $inbox;
    }

    /**
     * @return array|bool
     */
    public function searchNewEmails()
    {
        if (!$inbox = $this->getInbox())
            return false;

        $newEmails = imap_search($inbox,"UNSEEN SUBJECT '$this->searchKey'");

        imap_close($inbox);

        return $newEmails;
    }

    /**
     * @param $inbox
     * @param $emailNumber
     * @return array|bool
     */
    public function getInformationAboutSender($inbox, $emailNumber)
    {
        try {
            $message = imap_fetchbody($inbox, $emailNumber, 2);
            $header = imap_headerinfo($inbox, $emailNumber);
            $senderEmail = $header->from[0]->mailbox . "@" . $header->from[0]->host;
            $senderName = $header->from[0]->personal;
        } catch (\Exception $exception) {
            \Yii::$app->getSession()->setFlash('error', "Не вдалося отримати інформацію про відправника листа #$emailNumber");
            return false;
        }

        return ['email' => $senderEmail, 'name' => $senderName];
    }

    /**
     * @param $inbox
     * @param $emailNumber
     * @return bool|object
     */
    public function getEmailStructure($inbox, $emailNumber)
    {
        try {
            $structure = imap_fetchstructure($inbox, $emailNumber);
        } catch (\Exception $exception) {
            \Yii::$app->getSession()->setFlash('error', "Не вдалося отримати структуру листа #$emailNumber");
            return false;
        }

        return $structure;
    }

    /**
     * @param $dir
     * @param $participantId
     * @param $dataSender
     * @return bool
     */
    public function createNewMaterial($dir, $participantId, $dataSender)
    {
        $material = new Material();
        $material->dir = $dir;
        $material->participant_id = $participantId;

        if ($material->save())
            return true;
        \Yii::$app->getSession()->setFlash('error', "Не вдалося створити матеріл листа від ".$dataSender['email']);
        return false;
    }

    /**
     * @param $dataSender
     * @return bool|int|mixed
     */
    public function createNewParticipant($dataSender)
    {
        $participant = Participant::find()->where(['email' => $dataSender['email']])->one();

        if (count($participant) != 0)
            return $participant->id;

        $participant = new Participant();
        $participant->email = $dataSender['email'];
        $participant->name  = $dataSender['name'];

        if ($participant->save())
            return $participant->id;
        \Yii::$app->getSession()->setFlash('error', "Не вдалося створити модель учасника листа від ".$dataSender['email']);
        return false;
    }

    /**
     * @param $newEmails
     * @return bool
     */
    public function readingEmail($newEmails)
    {
        if($newEmails)
        {
            if (!$inbox = $this->getInbox())
                return false;

            $count = 1;

            if (is_array($newEmails) && (count($newEmails) > 0))
                rsort($newEmails);

            foreach($newEmails as $emailNumber)
            {
                if (!$emailStructure = $this->getEmailStructure($inbox, $emailNumber))
                    return false;

                if (!$dataSender = $this->getInformationAboutSender($inbox, $emailNumber))
                    return false;

                if (!$dir = $this->createFile($emailStructure, $inbox, $emailNumber, $dataSender)) {

                    imap_close($inbox);
                    return false;
                }

                if (!$participantId = $this->createNewParticipant($dataSender))
                    return false;

                if (!$this->createNewMaterial($dir, $participantId, $dataSender))
                    return false;

                if($count++ >= $this->emailReadingLimit) break;

            }

            imap_close($inbox);

            return true;
        }
        \Yii::$app->getSession()->setFlash('error', 'Не знайдено нових листів');
        return false;
    }


    /**
     * @param $structure
     * @param $inbox
     * @param $emailNumber
     * @param $dataSender
     * @return bool|string
     */
    public function createFile($structure, $inbox, $emailNumber, $dataSender)
    {
        if(isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );

                if($structure->parts[$i]->ifdparameters)
                {
                    foreach($structure->parts[$i]->dparameters as $object)
                    {
                        if(strtolower($object->attribute) == 'filename')
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }

                if($structure->parts[$i]->ifparameters)
                {
                    foreach($structure->parts[$i]->parameters as $object)
                    {

                        if(strtolower($object->attribute) == 'name')
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $object->value;
                        }
                    }
                }

                if($attachments[$i]['is_attachment'])
                {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $emailNumber, $i+1);

                    /* 3 = BASE64 encoding */
                    if($structure->parts[$i]->encoding == 3)
                    {
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }
                    /* 4 = QUOTED-PRINTABLE encoding */
                    elseif($structure->parts[$i]->encoding == 4)
                    {
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }

            }
        }
        if (empty($attachments[1]['is_attachment'])) {

            \Yii::$app->getSession()->setFlash('info', "Не зайнайдено прикріплених файлів в листі від ".$dataSender['email']);
            return false;
        }

        $dirPath = \Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].$emailNumber.'/';
        if (!file_exists(\Yii::$app->getBasePath().\Yii::$app->params['PathToAttachments'].$emailNumber)) {
            try {
                mkdir($dirPath);
            } catch (\Exception $exception) {
                \Yii::$app->getSession()->setFlash('error', "Не вдалося створити директорію $dirPath для листа від ".$dataSender['email']);
                return false;
            }
        }

        foreach($attachments as $attachment)
        {
            if($attachment['is_attachment'] == 1)
            {
                $filename = imap_utf8($attachment['name']);
                if(empty($filename)) $filename = imap_utf8($attachment['filename']);

                if(empty($filename)) $filename = time() . ".dat";

                /* prefix the email number to the filename in case two emails
                 * have the attachment with the same file name.
                 */
                $filename = $emailNumber . "_" . $filename;

                $filePath = $dirPath . $filename;

                try {
                    $fp = fopen($filePath, "w+");

                    fwrite($fp, $attachment['attachment']);
                    fclose($fp);
                } catch (\Exception $exception) {
                    \Yii::$app->getSession()->setFlash('error', "Не вдалося створити файли для листа від ".$dataSender['email']);
                    return false;
                }

            }

        }

        return '/'.$emailNumber.'/';
    }

}