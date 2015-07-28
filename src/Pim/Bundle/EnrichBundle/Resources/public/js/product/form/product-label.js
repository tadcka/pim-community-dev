'use strict';
/**
 * Product label extension
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    ['pim/form', 'pim/user-context', 'oro/mediator'],
    function (BaseForm, UserContext, mediator) {
        return BaseForm.extend({
            tagName: 'span',
            className: 'product-label',
            configure: function () {
                this.listenTo(UserContext, 'change:catalogLocale', this.render);
                this.listenTo(mediator, 'pim_enrich:form:entity:post_update', this.render);

                return BaseForm.prototype.configure.apply(this, arguments);
            },
            render: function () {
                var meta = this.getFormData().meta;

                if (meta && meta.label) {
                    this.$el.text(meta.label[UserContext.get('catalogLocale')]);
                }

                return this;
            }
        });
    }
);
