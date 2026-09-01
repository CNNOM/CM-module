<?php
declare(strict_types=1);

namespace Cloudmill\App\EventHandlers;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Cloudmill\App\ExceptionHandlers\AppExceptionHandler;
use CloudMill\App\Helpers\IBlockHelper;

final class AdminPanel
{
    private const MEDIA_IBLOCK_CODE = 'media_center';
    private const MEDIA_IBLOCK_ID = 11;
    private const DELIVERY_IBLOCK_CODE = 'customers_delivery';
    private const PAGE_SETTINGS_IBLOCK_CODE = 'page_settings';
    private const REFUND_INSTRUCTION_PROPERTY_CODE = 'CUSTOMERS_REFUND_INSTRUCTION';

    public static function OnAdminContextMenuShow(&$fields): bool
    {
        try {
            Loader::includeModule("iblock");
        } catch (LoaderException $e) {
            AppExceptionHandler::handle($e);
            return true;
        }

        $elementId = self::getElementIdFromUrl();
        if (!$elementId) return true;

        $url = self::getElementDetailPageUrl($elementId);
        if (!$url) return true;

        $fields[] = [
            'TEXT' => 'Просмотреть',
            'TITLE' => 'Просмотреть на сайте',
            'LINK' => $url,
        ];

        return true;
    }

    public static function showMediaDetailHelp(): void
    {
        global $APPLICATION;

        $requestUri = $APPLICATION->GetCurUri();
        if (!str_contains($requestUri, '/bitrix/admin/iblock_element_edit.php')) {
            return;
        }

        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        if ($iblockId !== IBlockHelper::getID(self::MEDIA_IBLOCK_CODE)) {
            return;
        }

        echo <<<HTML
<div id="media-detail-help-message" class="adm-info-message-wrap" style="display:none;">
    <div class="adm-info-message" style="max-width: 980px;">
        <b>Куда что загружать</b><br>
        Фотографии (`PHOTOS`) — сюда загружать фото для статьи<br>
        Видео на детальной странице (`VIDEO_DETAIL`) — сюда выбирать видео для статьи<br>
        Цитаты (`QUOTES`) — сюда заполнять цитаты<br>
        Превью видео (`VIDEO`) — это другое поле, для этих вставок не использовать<br><br>

        <b>Как вызывать в тексте статьи в свойстве "Детальное описание"</b><br><br>

        Одно фото:<br>
        <code>&lt;!-- photo-1 --&gt;</code><br><br>

        Слайдер из фото:<br>
        <code>&lt;!-- slider-1-3 --&gt;</code><br><br>

        Видео:<br>
        <code>&lt;!-- video-1 --&gt;</code><br><br>

        Видео с обложкой из фото:<br>
        <code>&lt;!-- video-1-prev-4 --&gt;</code><br><br>

        Цитата:<br>
        <code>&lt;!-- quotes-1 --&gt;</code><br><br>

        <b>Как заполнять цитаты</b><br>
        Текст цитаты — в значение поля `Цитаты` (`QUOTES`)<br>
        Автор и должность — в описание к этой же цитате<br><br>

        Пример описания:<br>
        <code>Иванова Роза Петровна — Генеральный директор АО «Лента»</code><br><br>

        <b>Важно</b><br>
        Нумерация начинается с `1`<br>
        Номер в комментарии должен соответствовать порядку элементов в поле<br>
        Комментарии нужно вставлять в свойство "Детальное описание" точно в таком виде, как в примерах
    </div>
</div>
<script>
BX.ready(function () {
    var helpNode = document.getElementById('media-detail-help-message');
    if (!helpNode) {
        return;
    }

    var form = document.getElementById('form_element_11_form');
    if (form && form.parentNode) {
        if (form.nextSibling) {
            form.parentNode.insertBefore(helpNode, form.nextSibling);
        } else {
            form.parentNode.appendChild(helpNode);
        }
    }

    helpNode.style.display = '';
});
</script>
HTML;
    }

    public static function showCustomersDeliveryHelp(): void
    {
        global $APPLICATION;

        $requestUri = $APPLICATION->GetCurUri();
        if (!str_contains($requestUri, '/bitrix/admin/iblock_element_edit.php')) {
            return;
        }

        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        if ($iblockId !== IBlockHelper::getID(self::DELIVERY_IBLOCK_CODE)) {
            return;
        }

        $formId = 'form_element_' . $iblockId . '_form';

        echo <<<HTML
<div id="customers-delivery-help-message" class="adm-info-message-wrap" style="display:none;">
    <div class="adm-info-message" style="max-width: 980px;">
        <b>Перед вставкой тегов</b><br>
        Переключите редактор поля "Детальное описание" в режим "Исходный код" (HTML) — в визуальном режиме редактор добавляет лишние теги <code>&lt;p&gt;</code>/<code>&lt;br&gt;</code> вокруг строк<br><br>

        <b>Доступные теги</b><br><br>

        Обёртка блока с текстом:<br>
        <code>[paragraph_delivery]...[/paragraph_delivery]</code><br><br>

        Серый текст (подпись к полю):<br>
        <code>[grey_text_delivery]Адрес склада[/grey_text_delivery]</code><br><br>

        Обычный текст:<br>
        <code>[text_delivery]Чувашская Республика, г. Новочебоксарск, ул. 10 Пятилетки, 23[/text_delivery]</code><br><br>

        Маркированный список:<br>
        <code>[list_delivery]</code><br>
        <code>[list_item_delivery]Будни: 9:30–11:30, 13:00–15:30[/list_item_delivery]</code><br>
        <code>[list_item_delivery]Въезд на территорию по пропускам[/list_item_delivery]</code><br>
        <code>[/list_delivery]</code><br><br>

        Кнопка-ссылка (открывается в новой вкладке):<br>
        <code>[button_delivery href="https://yandex.ru/maps/..."]Схема проезда[/button_delivery]</code><br><br>

        Примечание с иконкой:<br>
        <code>[note_delivery]Оплата транспортных расходов осуществляется покупателем[/note_delivery]</code><br><br>

        <b>Как вкладывать теги друг в друга</b><br>
        `paragraph_delivery`, `button_delivery` и `note_delivery` — пишутся на верхнем уровне текста, не друг в друге<br>
        `grey_text_delivery`, `text_delivery` и `list_delivery` — можно как отдельно, так и внутри `paragraph_delivery`<br>
        `list_item_delivery` — только внутри `list_delivery`<br><br>

        <b>Важно</b><br>
        Любой тег можно записать в двух формах: <code>[tag]текст[/tag]</code> или <code>[tag content="текст"]</code><br>
        Если в тексте есть кавычки — используйте форму <code>[tag]...[/tag]</code>, иначе значение атрибута `content` оборвётся по первой кавычке<br><br>

        Пример полного блока:<br>
        <code>[paragraph_delivery]</code><br>
        <code>[grey_text_delivery]Адрес склада[/grey_text_delivery]</code><br>
        <code>[text_delivery]Чувашская Республика, г. Новочебоксарск, ул. 10 Пятилетки, 23[/text_delivery]</code><br>
        <code>[/paragraph_delivery]</code><br>
        <code>[button_delivery href="https://yandex.ru/maps/..."]Схема проезда[/button_delivery]</code><br>
        <code>[note_delivery]Оплата транспортных расходов осуществляется покупателем[/note_delivery]</code>
    </div>
</div>
<script>
BX.ready(function () {
    var helpNode = document.getElementById('customers-delivery-help-message');
    if (!helpNode) {
        return;
    }

    var form = document.getElementById('{$formId}');
    if (form && form.parentNode) {
        if (form.nextSibling) {
            form.parentNode.insertBefore(helpNode, form.nextSibling);
        } else {
            form.parentNode.appendChild(helpNode);
        }
    }

    helpNode.style.display = '';
});
</script>
HTML;
    }

    public static function showCustomersRefundHelp(): void
    {
        global $APPLICATION;

        $requestUri = $APPLICATION->GetCurUri();
        if (!str_contains($requestUri, '/bitrix/admin/iblock_element_edit.php')) {
            return;
        }

        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        if ($iblockId !== IBlockHelper::getID(self::PAGE_SETTINGS_IBLOCK_CODE)) {
            return;
        }

        $formId = 'form_element_' . $iblockId . '_form';
        $propertyCode = self::REFUND_INSTRUCTION_PROPERTY_CODE;

        echo <<<HTML
<div id="customers-refund-help-message" class="adm-info-message-wrap" style="display:none;">
    <div class="adm-info-message" style="max-width: 980px;">
        <b>Для какого поля</b><br>
        Свойство <code>{$propertyCode}</code> на этом элементе — "Покупателям | Возврат | Пошаговая инструкция возврата". Остальные свойства на этой странице теги ниже не используют<br><br>

        <b>Доступные теги</b><br><br>

        Заголовок раздела:<br>
        <code>[title_refund]Условия возврата[/title_refund]</code><br><br>

        Обёртка блока «Условия возврата» (вводный текст + список):<br>
        <code>[info_refund]...[/info_refund]</code><br><br>

        Вводный абзац сразу под заголовком:<br>
        <code>[lead_refund]Чтобы вернуть товар, пожалуйста, выполните следующий порядок действий:[/lead_refund]</code><br><br>

        Обычный текст:<br>
        <code>[text_refund]Обязательные поля формы:[/text_refund]</code><br><br>

        Маркированный список:<br>
        <code>[list_refund]</code><br>
        <code>[list_item_refund]ФИО[/list_item_refund]</code><br>
        <code>[list_item_refund]Контактное лицо[/list_item_refund]</code><br>
        <code>[/list_refund]</code><br><br>

        Пронумерованный шаг процедуры (обёртка):<br>
        <code>[step_refund]...[/step_refund]</code><br><br>

        Номер шага и его текст:<br>
        <code>[step_num_refund num="1"]Верните товар в оригинальной упаковке[/step_num_refund]</code><br><br>

        Примечание с иконкой:<br>
        <code>[note_refund]Расходы на доставку возвращаемого товара оплачиваются покупателем самостоятельно[/note_refund]</code><br><br>

        <b>Как вкладывать теги друг в друга</b><br>
        `title_refund`, `info_refund`, `lead_refund`, `text_refund`, `step_refund` и `note_refund` — пишутся на верхнем уровне текста<br>
        `lead_refund`, `text_refund` и `list_refund` — можно отдельно или внутри `info_refund`/`step_refund`<br>
        `list_item_refund` — только внутри `list_refund`<br>
        `step_num_refund` — только внутри `step_refund`, второй и последующий `text_refund`/`list_refund` внутри `step_refund` идут после `step_num_refund`<br><br>

        <b>Важно</b><br>
        Любой тег можно записать в двух формах: <code>[tag]текст[/tag]</code> или <code>[tag content="текст"]</code><br>
        Если в тексте есть кавычки — используйте форму <code>[tag]...[/tag]</code>, иначе значение атрибута `content` оборвётся по первой кавычке<br>
        У `step_num_refund` обязателен атрибут `num` — номер шага (`num="1"`, `num="2"`...)<br><br>

        Пример полного блока для свойства <code>{$propertyCode}</code>:<br>
        <code>[title_refund]Условия возврата[/title_refund]</code><br>
        <code>[info_refund]</code><br>
        <code>[lead_refund]Вы можете оформить возврат или обмен товара надлежащего качества, если соблюдены следующие условия:[/lead_refund]</code><br>
        <code>[list_refund]</code><br>
        <code>[list_item_refund]Товар не использовался и сохранил первоначальный вид, упаковку и ярлыки.[/list_item_refund]</code><br>
        <code>[/list_refund]</code><br>
        <code>[/info_refund]</code><br>
        <code>[title_refund]Процедура возврата[/title_refund]</code><br>
        <code>[lead_refund]Чтобы вернуть товар, пожалуйста, выполните следующий порядок действий:[/lead_refund]</code><br>
        <code>[step_refund]</code><br>
        <code>[step_num_refund num="1"]Обратитесь к вашему персональному менеджеру или напишите по почте &lt;a href="mailto:kachestvo@lentacheb.ru"&gt;kachestvo@lentacheb.ru&lt;/a&gt;[/step_num_refund]</code><br>
        <code>[/step_refund]</code><br>
        <code>[step_refund]</code><br>
        <code>[step_num_refund num="2"]Предоставьте заполненную форму заявления на возврат вместе с документом, подтверждающим покупку.[/step_num_refund]</code><br>
        <code>[text_refund]Обязательные поля формы:[/text_refund]</code><br>
        <code>[list_refund]</code><br>
        <code>[list_item_refund]ФИО[/list_item_refund]</code><br>
        <code>[/list_refund]</code><br>
        <code>[/step_refund]</code><br>
        <code>[note_refund]Расходы на доставку возвращаемого товара оплачиваются покупателем самостоятельно, если иное не предусмотрено условиями конкретной акции или программы лояльности.[/note_refund]</code>
    </div>
</div>
<script>
BX.ready(function () {
    var helpNode = document.getElementById('customers-refund-help-message');
    if (!helpNode) {
        return;
    }

    var form = document.getElementById('{$formId}');
    if (form && form.parentNode) {
        if (form.nextSibling) {
            form.parentNode.insertBefore(helpNode, form.nextSibling);
        } else {
            form.parentNode.appendChild(helpNode);
        }
    }

    helpNode.style.display = '';
});
</script>
HTML;
    }

    private static function getElementIdFromUrl(): ?int
    {
        global $APPLICATION;
        $curUri = $APPLICATION->GetCurUri();
        if (!str_contains($curUri, 'bitrix/admin/iblock_element_edit.php')) return null;

        if (preg_match('#&ID=(\d+)&#', $curUri, $matches) && $matches[1]) {
            return (int)$matches[1];
        }
        return null;
    }

    private static function getElementDetailPageUrl(int $elementId): ?string
    {
        $element = \CIBlockElement::GetByID($elementId)->GetNext();
        return $element['DETAIL_PAGE_URL'] ?? null;
    }
}
