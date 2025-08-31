import type React from 'react';
import { useRef, useMemo } from 'react';
import styles from './List.module.css';
import { selectDragging } from '@/features/ui/uiSlice';
import { useAppSelector } from '@/app/hooks';
import { Box, Callout, Flex, Skeleton } from '@radix-ui/themes';
import clsx from 'clsx';
import ListItem from '@/components/list/ListItem';
import type { ComponentsList } from '@/types/Component';
import type { PatternsList } from '@/types/Pattern';
import type { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import { InfoCircledIcon } from '@radix-ui/react-icons';

export interface ListProps {
  items: ComponentsList | PatternsList | undefined;
  isLoading: boolean;
  type:
    | LayoutItemType.COMPONENT
    | LayoutItemType.PATTERN
    | LayoutItemType.DYNAMIC;
  label: string;
}

const List: React.FC<ListProps> = (props) => {
  const { items, isLoading, type, label } = props;
  const listElRef = useRef<HTMLDivElement>(null);
  const { isDragging } = useAppSelector(selectDragging);

  // Sort items and convert to array.
  const sortedItems = useMemo(() => {
    return items
      ? Object.entries(items).sort(([, a], [, b]) =>
          a.name.localeCompare(b.name),
        )
      : [];
  }, [items]);

  if ((!items || !Object.keys(items).length) && !isLoading) {
    return (
      <Callout.Root size="1" variant="soft" color="gray" my="3">
        <Flex align="center" gapX="2">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          <Callout.Text size="1">No items to show in {label}</Callout.Text>
        </Flex>
      </Callout.Root>
    );
  }

  return (
    <div className={clsx('listContainer', styles.listContainer)}>
      <Box className={isDragging ? 'list-dragging' : ''}>
        <Skeleton
          data-testid="xb-components-library-loading"
          loading={isLoading}
          height="1.2rem"
          width="100%"
          my="3"
        >
          <Flex direction="column" width="100%" ref={listElRef} role="list">
            {sortedItems &&
              sortedItems.map(([id, item]) => (
                <ListItem item={item} key={id} type={type} />
              ))}
          </Flex>
        </Skeleton>
        <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
        <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
      </Box>
    </div>
  );
};

export default List;
