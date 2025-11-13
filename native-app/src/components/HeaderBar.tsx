import { FC, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Modal } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  activeTab: string;
  onMenuSelect: (key: string) => void;
  onLogout: () => void;
  menuItems: { key: string; label: string }[];
}

export const HeaderBar: FC<Props> = ({ activeTab, onMenuSelect, onLogout, menuItems }) => {
  const [open, setOpen] = useState(false);

  const handleSelect = (key: string) => {
    setOpen(false);
    onMenuSelect(key);
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>GlitchaHitch</Text>
      <TouchableOpacity style={styles.menuButton} onPress={() => setOpen(true)}>
        <Text style={styles.menuText}>⋮</Text>
      </TouchableOpacity>

      <Modal transparent visible={open} animationType="fade" onRequestClose={() => setOpen(false)}>
        <TouchableOpacity style={styles.backdrop} onPress={() => setOpen(false)} />
        <View style={styles.menuSheet}>
          {menuItems.map((item) => (
            <TouchableOpacity key={item.key} style={styles.menuRow} onPress={() => handleSelect(item.key)}>
              <Text style={[styles.menuLabel, activeTab === item.key && styles.menuLabelActive]}>
                {item.label}
              </Text>
            </TouchableOpacity>
          ))}
          <TouchableOpacity
            style={styles.menuRow}
            onPress={() => {
              setOpen(false);
              onLogout();
            }}
          >
            <Text style={styles.logout}>Logout</Text>
          </TouchableOpacity>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    backgroundColor: '#fff',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e1e1e1',
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
  },
  menuButton: {
    padding: spacing.sm,
  },
  menuText: {
    fontSize: 20,
    color: palette.text,
  },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.3)',
  },
  menuSheet: {
    position: 'absolute',
    top: 60,
    right: spacing.md,
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingVertical: spacing.sm,
    width: 200,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 10,
    elevation: 8,
  },
  menuRow: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  menuLabel: {
    color: palette.text,
    fontSize: 14,
  },
  menuLabelActive: {
    fontWeight: '700',
  },
  logout: {
    color: palette.danger,
    fontWeight: '600',
  },
});
