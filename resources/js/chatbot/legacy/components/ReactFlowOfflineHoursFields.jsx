import React from "react";
import { Card, Form, Switch, Select, Input, TimePicker, Typography } from "antd";
import { ClockCircleOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import customParseFormat from "dayjs/plugin/customParseFormat";

dayjs.extend(customParseFormat);

const { TextArea } = Input;
const { Text } = Typography;

export const DEFAULT_OFFLINE_MESSAGE = `We're offline right now 😴 — back at 9:00 AM.

Your message is saved and our team will pick it up first thing in the morning! You can still browse our menu below.`;

export const OFFLINE_HOURS_DEFAULTS = {
  enableOfflineHours: false,
  timezone: "Asia/Kolkata",
  onlineFrom: "09:00",
  onlineUntil: "21:00",
  offlineMessage: DEFAULT_OFFLINE_MESSAGE,
};

const TIME_FORMAT = "HH:mm";

/** Normalize stored HH:mm / dayjs values for TimePicker. */
export const toOfflineTimeValue = (value) => {
  if (!value) return null;
  if (dayjs.isDayjs(value)) return value;
  const parsed = dayjs(String(value), ["HH:mm", "H:mm", "HH:mm:ss"], true);
  return parsed.isValid() ? parsed : null;
};

/** Flatten TimePicker dayjs → HH:mm before persisting node data. */
export const normalizeOfflineHoursForSave = (values = {}) => {
  const enabled = !!values.enableOfflineHours;
  const fromRaw = values.onlineFrom;
  const untilRaw = values.onlineUntil;

  const onlineFrom = dayjs.isDayjs(fromRaw)
    ? fromRaw.format(TIME_FORMAT)
    : String(fromRaw || OFFLINE_HOURS_DEFAULTS.onlineFrom).slice(0, 5);
  const onlineUntil = dayjs.isDayjs(untilRaw)
    ? untilRaw.format(TIME_FORMAT)
    : String(untilRaw || OFFLINE_HOURS_DEFAULTS.onlineUntil).slice(0, 5);

  return {
    enableOfflineHours: enabled,
    timezone: values.timezone || OFFLINE_HOURS_DEFAULTS.timezone,
    onlineFrom: onlineFrom || OFFLINE_HOURS_DEFAULTS.onlineFrom,
    onlineUntil: onlineUntil || OFFLINE_HOURS_DEFAULTS.onlineUntil,
    offlineMessage:
      (values.offlineMessage && String(values.offlineMessage).trim()) ||
      OFFLINE_HOURS_DEFAULTS.offlineMessage,
  };
};

const timeRule = {
  validator: (_, value) => {
    if (!value) {
      return Promise.reject(new Error("Enter time (HH:mm)"));
    }
    if (dayjs.isDayjs(value) && value.isValid()) {
      return Promise.resolve();
    }
    const parsed = dayjs(String(value), ["HH:mm", "H:mm"], true);
    return parsed.isValid()
      ? Promise.resolve()
      : Promise.reject(new Error("Use 24-hour time, e.g. 09:00"));
  },
};

const ReactFlowOfflineHoursFields = () => {
  return (
    <Card
      title={
        <span>
          <ClockCircleOutlined style={{ marginRight: 8 }} />
          Offline hours
        </span>
      }
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        name="enableOfflineHours"
        label="Send a different message outside business hours"
        valuePropName="checked"
      >
        <Switch />
      </Form.Item>
      {/* Keep fields mounted so enable/disable does not drop saved times/message. */}
      <Form.Item
        noStyle
        shouldUpdate={(prev, curr) =>
          prev.enableOfflineHours !== curr.enableOfflineHours
        }
      >
        {({ getFieldValue }) => {
          const enabled = !!getFieldValue("enableOfflineHours");
          return (
            <div style={{ display: enabled ? "block" : "none" }}>
              <Form.Item
                name="timezone"
                label="Timezone"
                rules={
                  enabled
                    ? [{ required: true, message: "Select timezone" }]
                    : []
                }
              >
                <Select>
                  <Select.Option value="Asia/Kolkata">
                    India (Asia/Kolkata)
                  </Select.Option>
                  <Select.Option value="UTC">UTC</Select.Option>
                </Select>
              </Form.Item>
              <Form.Item
                name="onlineFrom"
                label="Online from"
                extra="24-hour time"
                getValueProps={(value) => ({
                  value: toOfflineTimeValue(value),
                })}
                getValueFromEvent={(value) =>
                  value && value.isValid?.()
                    ? value.format(TIME_FORMAT)
                    : value
                }
                rules={enabled ? [timeRule] : []}
              >
                <TimePicker format={TIME_FORMAT} minuteStep={5} style={{ width: "100%" }} />
              </Form.Item>
              <Form.Item
                name="onlineUntil"
                label="Online until"
                extra="Messages after this time use the offline reply"
                getValueProps={(value) => ({
                  value: toOfflineTimeValue(value),
                })}
                getValueFromEvent={(value) =>
                  value && value.isValid?.()
                    ? value.format(TIME_FORMAT)
                    : value
                }
                rules={enabled ? [timeRule] : []}
              >
                <TimePicker format={TIME_FORMAT} minuteStep={5} style={{ width: "100%" }} />
              </Form.Item>
              <Form.Item
                name="offlineMessage"
                label="Offline message"
                extra="Sent instead of the normal greeting. Menu still follows if wired."
                rules={
                  enabled
                    ? [{ required: true, message: "Enter offline message" }]
                    : []
                }
              >
                <TextArea rows={6} showCount maxLength={1000} />
              </Form.Item>
              <Text type="secondary" style={{ fontSize: 12 }}>
                Inside these hours the normal welcome/text is sent. Outside
                hours the offline message is sent instead.
              </Text>
            </div>
          );
        }}
      </Form.Item>
    </Card>
  );
};

export default ReactFlowOfflineHoursFields;
