import React from "react";
import { Card, Form, Switch, Select, Input, Typography } from "antd";
import { ClockCircleOutlined } from "@ant-design/icons";

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
      <Form.Item
        noStyle
        shouldUpdate={(prev, curr) =>
          prev.enableOfflineHours !== curr.enableOfflineHours
        }
      >
        {({ getFieldValue }) =>
          getFieldValue("enableOfflineHours") ? (
            <>
              <Form.Item
                name="timezone"
                label="Timezone"
                rules={[{ required: true, message: "Select timezone" }]}
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
                extra="24-hour time, e.g. 09:00"
                rules={[{ required: true, message: "Enter start time" }]}
              >
                <Input placeholder="09:00" maxLength={5} />
              </Form.Item>
              <Form.Item
                name="onlineUntil"
                label="Online until"
                extra="24-hour time, e.g. 21:00"
                rules={[{ required: true, message: "Enter end time" }]}
              >
                <Input placeholder="21:00" maxLength={5} />
              </Form.Item>
              <Form.Item
                name="offlineMessage"
                label="Offline message"
                extra="Sent instead of the normal greeting. Menu still follows if wired."
                rules={[{ required: true, message: "Enter offline message" }]}
              >
                <TextArea rows={6} showCount maxLength={1000} />
              </Form.Item>
              <Text type="secondary" style={{ fontSize: 12 }}>
                Inside these hours the normal welcome/text is sent.
              </Text>
            </>
          ) : null
        }
      </Form.Item>
    </Card>
  );
};

export default ReactFlowOfflineHoursFields;
