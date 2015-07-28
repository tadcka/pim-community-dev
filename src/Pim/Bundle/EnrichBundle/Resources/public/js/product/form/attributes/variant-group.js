'use strict';
/**
 * Variant group extension
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        'jquery',
        'underscore',
        'pim/form',
        'pim/field-manager',
        'pim/fetcher-registry',
        'oro/mediator',
        'text!pim/template/product/tab/attribute/variant-group'
    ],
    function ($, _, BaseForm, FieldManager, FetcherRegistry, mediator, variantGroupTemplate) {
        return BaseForm.extend({
            template: _.template(variantGroupTemplate),
            configure: function () {
                this.listenTo(mediator, 'pim_enrich:form:field:extension:add', this.addExtension);

                return BaseForm.prototype.configure.apply(this, arguments);
            },
            addExtension: function (event) {
                var product = this.getFormData();
                if (!product.variant_group) {
                    return;
                }

                event.promises.push(
                    FetcherRegistry.getFetcher('variant-group').fetch(product.variant_group)
                        .done(_.bind(function (variantGroup) {
                            var deferred = $.Deferred();
                            var field = event.field;
                            if (variantGroup.values && _.contains(_.keys(variantGroup.values), field.attribute.code)) {
                                var $element = this.template({
                                    variantGroup: variantGroup
                                });

                                field.setEditable(false);
                                field.addElement('footer', 'updated_by', $element);
                            }
                            deferred.resolve();

                            return deferred.promise();
                        }, this))
                );

                return this;
            }
        });
    }
);
