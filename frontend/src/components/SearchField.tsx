import { Close, Search } from '@mui/icons-material';
import { IconButton, InputAdornment, TextField, Tooltip } from '@mui/material';

interface SearchFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
}

export function SearchField({ label, value, onChange }: SearchFieldProps) {
  return (
    <TextField
      label={label}
      type="text"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      sx={{ width: { xs: '100%', md: 300 } }}
      inputProps={{ role: 'searchbox' }}
      InputProps={{
        startAdornment: (
          <InputAdornment position="start">
            <Search fontSize="small" />
          </InputAdornment>
        ),
        endAdornment: value ? (
          <InputAdornment position="end">
            <Tooltip title="Limpar busca">
              <IconButton
                aria-label="Limpar busca"
                edge="end"
                size="small"
                onClick={() => onChange('')}
              >
                <Close fontSize="small" />
              </IconButton>
            </Tooltip>
          </InputAdornment>
        ) : undefined,
      }}
    />
  );
}
