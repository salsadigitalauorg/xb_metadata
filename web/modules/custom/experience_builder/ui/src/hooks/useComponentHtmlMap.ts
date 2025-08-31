import { useEffect } from 'react';
import { mapComponents, mapRegions, mapSlots } from '@/utils/function-utils';
import { useDataToHtmlMapUpdater } from '@/features/layout/preview/DataToHtmlMapContext';

export function useComponentHtmlMap(iframe: HTMLIFrameElement | null) {
  const { updateRegionsMap, updateComponentsMap, updateSlotsMap } =
    useDataToHtmlMapUpdater();

  const pendingTemplates = iframe?.contentDocument?.querySelectorAll(
    'template[data-astro-template]',
  ).length;

  useEffect(() => {
    const iframeDocument = iframe?.contentDocument;
    if (!iframeDocument || !iframeDocument.body) {
      return;
    }
    updateRegionsMap(mapRegions(iframeDocument));
    updateComponentsMap(mapComponents(iframeDocument));
    updateSlotsMap(mapSlots(iframeDocument));
  }, [
    iframe,
    updateComponentsMap,
    updateRegionsMap,
    updateSlotsMap,
    pendingTemplates,
  ]);
}
