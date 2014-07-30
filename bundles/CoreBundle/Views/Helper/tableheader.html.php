<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$defaultOrder = (!empty($default)) ? $orderBy : "";
$order        = (!empty($order)) ? $order : $app->getSession()->get("mautic.{$sessionVar}.orderby", $defaultOrder);
$dir          = (!empty($dir))? $dir : $app->getSession()->get("mautic.{$sessionVar}.orderbydir", "ASC");
$filters      = (!empty($filters)) ? $filters : $app->getSession()->get("mautic.{$sessionVar}.filters", array());
$target       = (!empty($target)) ? $target : '.bundle-list';
$tmpl         = (!empty($tmpl)) ? $tmpl : 'list';
?>
<th<?php echo (!empty($class)) ? ' class="' . $class . '"': ""; ?>>
    <div class="thead-filter">
        <?php if (!empty($orderBy)): ?>
        <a href="javascript: void(0);" onclick="Mautic.reorderTableData(
                            '<?php echo $sessionVar; ?>',
                            '<?php echo $orderBy; ?>',
                            '<?php echo $tmpl; ?>',
                            '<?php echo $target; ?>');">
            <span><?php echo $view['translator']->trans($text); ?></span>
            <?php if ($order == $orderBy): ?>
            <i class="fa fa-sort-amount-<?php echo strtolower($dir); ?>"></i>
            <?php endif; ?>
        </a>
        <?php else: ?>
            <span><?php echo $view['translator']->trans($text); ?></span>
        <?php endif; ?>

        <?php if (!empty($filterBy)): ?>
        <?php $value = (isset($filters[$filterBy])) ? $filters[$filterBy]['value'] : ''; ?>
        <div class="input-group">
            <?php $toggle = (!empty($dataToggle)) ? ' data-toggle="'.$dataToggle.'"' : ""; ?>
            <input type="text" placeholder="<?php echo $view['translator']->trans('mautic.core.form.thead.filter'); ?>"
                   autocomplete="off" class="form-control input-sm" value="<?php echo $value; ?>"<?php echo $toggle; ?>
                   onchange="Mautic.filterTableData(
                    '<?php echo $sessionVar; ?>',
                    '<?php echo $filterBy; ?>',
                    this.value,
                    '<?php echo $tmpl; ?>',
                    '<?php echo $target; ?>'
                   );" />
            <?php $inputClass =  (!empty($value)) ? 'fa-times' : 'fa-filter'; ?>
            <span class="input-group-btn">
                <button class="btn btn-default btn-sm" onclick="Mautic.filterTableData(
                    '<?php echo $sessionVar; ?>',
                    '<?php echo $filterBy; ?>',
                    <?php if (!empty($value)): ?>
                    '',
                    <?php else: ?>
                    this.value,
                    <?php endif; ?>
                    '<?php echo $tmpl; ?>',
                    '<?php echo $target; ?>'
                   );"><i class="fa fa-fw fa-lg <?php echo $inputClass; ?>"></i></button>
            </span>
        </div>
        <?php endif; ?>
    </div>
</th>