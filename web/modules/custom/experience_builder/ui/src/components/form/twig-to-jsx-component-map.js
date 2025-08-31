import DrupalDetails from '@/components/form/components/drupal/DrupalDetails';
import DrupalForm from '@/components/form/components/drupal/DrupalForm';
import DrupalFormElement from '@/components/form/components/drupal/DrupalFormElement';
import DrupalFormElementLabel from '@/components/form/components/drupal/DrupalFormElementLabel';
import DrupalInput from '@/components/form/components/drupal/DrupalInput';
import DrupalPathWidget from '@/components/form/components/drupal/DrupalPathWidget';
import DrupalSelect from '@/components/form/components/drupal/DrupalSelect';
import DrupalTextArea from '@/components/form/components/drupal/DrupalTextArea';
import DrupalToggle from '@/components/form/components/drupal/DrupalToggle';
import DrupalVerticalTabs from '@/components/form/components/drupal/DrupalVerticalTabs';
import {
  DrupalContainerTextFormatFilterGuidelines,
  DrupalContainerTextFormatFilterHelp,
} from '@/components/form/components/drupal/DrupalContainerTextFormat';
import { DrupalRadioGroup } from '@/components/form/components/drupal/DrupalRadio';
import DrupalMediaLibraryWidgetContainer from '@/components/form/components/MediaLibraryWidgetContainer';
import XbText from '@/components/form/xb-components/XbText';
import XbBox from '@/components/form/xb-components/XbBox';

// This is where we map the Drupal Twig templates to the corresponding JSX component.
// @see \Drupal\experience_builder\Hook\SemiCoupledThemeEngineHooks::themeSuggestionsAlter()
// @see docs/semi-coupled-theme-engine.md
// @see themes/engines/semi_coupled/README.md
// @see themes/xb_stark/templates/process_as_jsx/

const twigToJSXComponentMap = {
  'drupal-container--text-format-filter-guidelines':
    DrupalContainerTextFormatFilterGuidelines,
  'drupal-container--text-format-filter-help':
    DrupalContainerTextFormatFilterHelp,
  'drupal-details': DrupalDetails,
  'drupal-form': DrupalForm,
  'drupal-form-element': DrupalFormElement,
  'drupal-form-element-label': DrupalFormElementLabel,
  'drupal-input': DrupalInput,
  'drupal-input--checkbox--inwidget-boolean-checkbox': DrupalToggle,
  'drupal-input--url': DrupalInput,
  'drupal-input--textfield--inwidget-path': DrupalPathWidget,
  'drupal-radios': DrupalRadioGroup,
  'drupal-select': DrupalSelect,
  'drupal-textarea': DrupalTextArea,
  'drupal-vertical-tabs': DrupalVerticalTabs,
  'drupal-container--media-library-widget': DrupalMediaLibraryWidgetContainer,
  'xb-text': XbText,
  'xb-box': XbBox,
};

export default twigToJSXComponentMap;
