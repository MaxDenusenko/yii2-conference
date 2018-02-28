<?php

use yii\helpers\Html;
use yii\widgets\LinkPager;

?>

<main id="content" role="main" class="span9">
    <!-- Begin Content -->
    <div class="blog-featured" itemscope itemtype="https://schema.org/Blog">

        <?php if(!empty($matherial)): ?>
            <?php if (!empty($conferences)) : ?>
                <?= Html::dropDownList('Hi', 'w', \yii\helpers\ArrayHelper::getValue($conferences, function ($conferences, $defaultValue) {
                    $arr = [];
                    foreach ($conferences as $conference) {
                        $arr[\yii\helpers\Url::to(['/matherial/conference', 'id' => $conference->id])] = $conference->name;
                    }
                    return $arr;
                }), ['onchange' => "window.location.href=this.options[this.selectedIndex].value", 'prompt' => 'Конференции', 'options' => [yii\helpers\Url::current([]) => ['Selected' => true]]]) ?>
            <?php endif; ?>

            <?php foreach ($matherial as $item): ?>
        <div class="items-leading clearfix">
            <div class="leading-0 clearfix" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">

                <h2 class="item-title" itemprop="headline">
                    <a href="<?=\yii\helpers\Url::to(['matherial/view', 'id' => $item->id])?>">
                        <?=$item->author?>
                    </a>
                </h2>

                <dl class="article-info muted">
                    <dt class="article-info-term">
                    </dt>
                    <?php if (!empty($item->category)) : ?>
                        <dd class="category-name">Категорія: <?= Html::a($item->category->name, ['category', 'id' => $item->category->id]) ?></dd>
                    <?php endif; ?>
                    <dd class="published">
                        <i class="far fa-calendar-alt"></i>
                        <time datetime="<?=$item->publisher_at?>" itemprop="datePublished">
                            Опубліковано: <?=$item->publisher_at?></time>
                    </dd>
                </dl>

                <p>
                    <?=$item->udk?><br />
                    <?=$item->author?><br />
                    <?=$item->university?><br />
                    e-mail: <?=$item->emailHtml?>
                </p>
                <p><strong><?=$item->material_name?></strong></p>

                <?=$item->top_anotation?>

                <div class="attachmentsContainer">

                    <div class="attachmentsList" id="attachmentsList_com_content_article_308">
                        <table>
                            <caption>Вкладення:</caption>
                            <thead>
                            <tr>
                                <th class="at_filename">Файл</th>
                                <th class="at_file_size">Розмір файла:</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr class="odd">
                                <td class="at_filename"><a href="<?=\yii\helpers\Url::to(['matherial/view-pdf', 'id' => $item->id])?>" target="_blank">Повний текст роботи</a></td>
                                <td class="at_file_size"><?=$item->fileSize?></td>
                            </tr>
                            </tbody></table>
                    </div>

                </div>



                <p class="readmore">
                    <a class="btn" href="<?=\yii\helpers\Url::to(['matherial/view', 'id' => $item->id])?>" itemprop="url">
                        <span class="icon-chevron-right"></span>
                        Детальніше...	</a>
                </p>

            </div>
        </div>

            <?php endforeach; ?>
        <?php endif;?>

        <div class="pagination">
            <?php
            echo LinkPager::widget([
                'pagination' => $pages,
                'nextPageLabel' => 'Туда',
                'prevPageLabel' => 'Сюда',
                'maxButtonCount' => 11,
            ]);
            ?>
        </div>

    </div>


    <!-- End Content -->
</main>

<?=\app\widgets\RSidebar::widget()?>