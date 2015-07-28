'use strict';
/**
 * Edit form
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @author    Filips Alps <filips@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        'underscore',
        'backbone',
        'text!pim/template/product/form',
        'pim/form',
        'oro/mediator',
        'pim/fetcher-registry',
        'pim/field-manager'
    ],
    function (
        _,
        Backbone,
        template,
        BaseForm,
        mediator,
        FetcherRegistry,
        FieldManager
    ) {
        var FormView = BaseForm.extend({
            template: _.template(template),
            initialize: function () {
                this.model = new Backbone.Model();

                BaseForm.prototype.initialize.apply(this, arguments);
            },
            configure: function () {
                mediator.clear('pim_enrich:form');

                return BaseForm.prototype.configure.apply(this, arguments);
            },
            render: function () {
                if (!this.configured) {
                    return this;
                }

                this.$el.html(
                    this.template({
                        product: this.getFormData()
                    })
                );

                return this.renderExtensions();
            },
            clearCache: function () {
                FetcherRegistry.clearAll();
                FieldManager.clearFields();
                this.render();
            }
        });

        return FormView;
    }
);
