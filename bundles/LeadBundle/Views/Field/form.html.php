<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'leadfield');
$userId = $form->vars['data']->getId();
if (!empty($userId)) {
    $field   = $form->vars['data']->getLabel();
    $header = $view['translator']->trans('mautic.lead.field.header.edit', array("%name%" => $field));
} else {
    $header = $view['translator']->trans('mautic.lead.field.header.new');
}
$view['slots']->set("headerTitle", $header);
?>
<div class="row">
    <div class="col-md-9">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <?php echo $header; ?>
                </h3>
            </div>
            <div class="panel-body">
            <?php echo $view['form']->start($form); ?>
            <?php echo $view['form']->row($form['label']); ?>
            <?php echo $view['form']->row($form['alias']); ?>
            <?php echo $view['form']->row($form['group']); ?>
            <?php
            $type          = $form['type']->vars['data'];
            $properties    = $form['properties']->vars['data'];
            $errors        = $form['properties']->vars['errors'];
            $feedbackClass = ($app->getRequest()->getMethod() == 'POST' && !empty($errors)) ? " has-error" : "";
            ?>

                <div class="row">
                    <div class="form-group  col-xs-12 col-sm-8 col-md-6<?php echo $feedbackClass; ?>">
                        <div id="leadfield_properties">
                            <?php
                            switch ($type):
                            case 'boolean':
                                echo $view->render('MauticLeadBundle:Field:properties_boolean.html.php', array(
                                    'yes' => isset($properties['yes']) ? $properties['yes'] : '',
                                    'no'  => isset($properties['no'])  ? $properties['no'] : ''
                                ));
                                break;
                            case 'lookup':
                                echo $view->render('MauticLeadBundle:Field:properties_lookup.html.php', array(
                                    'value' => isset($properties['list']) ? $properties['list'] : ''
                                ));
                                break;
                            case 'number':
                                echo $view->render('MauticLeadBundle:Field:properties_number.html.php', array(
                                    'roundMode' => isset($properties['roundmode']) ? $properties['roundmode'] : '',
                                    'precision' => isset($properties['precision']) ? $properties['precision'] : ''
                                ));
                                break;
                            case 'select':
                                echo $view->render('MauticLeadBundle:Field:properties_select.html.php', array(
                                    'value' => isset($properties['list']) ? $properties['list'] : ''
                                ));
                                break;
                            endswitch;
                            ?>
                        </div>
                        <?php echo $view['form']->errors($form['properties']); ?>
                    </div>
                </div>
                <?php $form['properties']->setRendered(); ?>

                <div id="field-templates" class="hide">
                    <?php echo $view->render('MauticLeadBundle:Field:properties_boolean.html.php'); ?>
                    <?php echo $view->render('MauticLeadBundle:Field:properties_lookup.html.php'); ?>
                    <?php echo $view->render('MauticLeadBundle:Field:properties_number.html.php'); ?>
                    <?php echo $view->render('MauticLeadBundle:Field:properties_select.html.php'); ?>
                </div>
            </div>
        </div>
        
    </div>
    <div class="col-md-3">
        <?php echo $view['form']->end($form); ?>
    </div>
</div>