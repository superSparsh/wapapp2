import React, { useState, useEffect } from "react";
import BuilderDrawer, { BuilderDrawerTitle } from "./BuilderDrawer.jsx";
import {
  Form,
  Input,
  Button,
  Upload,
  Radio,
  message,
  Space,
  Select,
  InputNumber,
} from "antd";
import { UploadOutlined, DeleteOutlined } from "@ant-design/icons";
import styled from "@emotion/styled";
import VariableHelper from "./VariableHelper";

const { TextArea } = Input;

const WrapperStyled = styled.div`
  .ant-form-item-control-input-content,
  .ant-upload-wrapper,
  .ant-radio-wrapper,
  .ant-form-item-label {
    font-family: "Poppins", "Open Sans", "Helvetica Neue", "Arial", "Helvetica",
      "Verdana", sans-serif;
  }
`;

const ReactFlowMediaModule = ({
  visible,
  onClose,
  onSave,
  nodeData = {},
  variables = [],
}) => {
  const [form] = Form.useForm();
  const [selectedMediaType, setSelectedMediaType] = useState("document");
  const [fileList, setFileList] = useState([]);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (visible && nodeData) {
      // Pre-populate form with existing node data
      form.setFieldsValue({
        label: nodeData.label || "Media Message",
        mediaType: nodeData.mediaType || "document",
        caption: nodeData.caption || "",
        fileName: nodeData.fileName || "",
        fileSize: nodeData.fileSize || null,
        duration: nodeData.duration || null,
        fileType: nodeData.fileType || "",
      });

      setSelectedMediaType(nodeData.mediaType || "document");

      // Set file list if there's existing file data
      if (nodeData.fileName) {
        setFileList([
          {
            uid: "-1",
            name: nodeData.fileName,
            status: "done",
            url: nodeData.fileUrl || "",
          },
        ]);
      }
    }
  }, [visible, nodeData, form]);

  const handleVariableSelect = (variableSyntax) => {
    const currentCaption = form.getFieldValue("caption") || "";
    const newCaption = `${currentCaption} ${variableSyntax}`;
    form.setFieldsValue({ caption: newCaption });
  };

  const handleFileChange = (info) => {
    setFileList(info.fileList);
  };

  const handleUpload = async (file) => {
    setUploading(true);

    try {
      const formData = new FormData();
      formData.append("file", file);
      formData.append("mediaType", selectedMediaType);

      const csrfToken =
        localStorage.getItem("csrfToken") ||
        document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute("content");

      const response = await fetch("/chatbotfileupload", {
        method: "POST",
        body: formData,
        headers: {
          "X-CSRF-TOKEN": csrfToken || "",
        },
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          message.success("File uploaded successfully!");
          return {
            fileName: result.file_name || result.fileName,
            fileSize: result.file_size || result.fileSize,
            fileUrl: result.path || result.fileUrl,
            fileType: result.file_type || result.fileType,
            duration: result.duration,
            thumbnail: result.thumbnail_path,
          };
        } else {
          throw new Error(result.error || "Upload failed");
        }
      } else {
        throw new Error("Upload failed");
      }
    } catch (error) {
      message.error("Failed to upload file");
      console.error("Upload error:", error);
      return null;
    } finally {
      setUploading(false);
    }
  };

  const onFinish = async (values) => {
    try {
      // Check if file is uploaded (REQUIRED VALIDATION)
      if (fileList.length === 0 || !fileList[0].originFileObj) {
        message.error(`Please upload a ${selectedMediaType} file`);
        return;
      }

      let fileData = null;

      // Handle file upload (required for all media types)
      if (fileList.length > 0 && fileList[0].originFileObj) {
        fileData = await handleUpload(fileList[0].originFileObj);
        if (!fileData) {
          message.error("Failed to upload file");
          return;
        }
      }

      const mediaData = {
        label: values.label,
        mediaType: values.mediaType,
        caption: selectedMediaType !== "audio" ? values.caption || "" : "", // Hide caption for audio
        fileName: fileData?.fileName || values.fileName || "",
        fileSize: fileData?.fileSize || values.fileSize || null,
        fileType: fileData?.fileType || values.fileType || "",
        fileUrl: fileData?.fileUrl || "",
        duration: fileData?.duration || values.duration || null,
        // Add any additional media-specific data
        ...(values.mediaType === "video" && {
          thumbnail: fileData?.thumbnail || values.thumbnail || "",
        }),
        // Mark if this is a placeholder node (no file uploaded)
        isPlaceholder: !fileData,
      };

      onSave(mediaData);
      message.success("Media message configured successfully!");
      onClose(); // Close the drawer after successful save
    } catch (error) {
      console.error("Error saving media message:", error);
      message.error("Failed to save media message");
    }
  };

  const onFinishFailed = (errorInfo) => {
    console.log("Failed:", errorInfo);
    message.error("Please check your input and try again.");
  };

  const mediaConstraints = {
    image: {
      size: 5,
      types: "image/jpeg,image/png,image/gif,image/webp",
      maxSize: 5 * 1024 * 1024, // 5MB
    },
    video: {
      size: 16,
      types: "video/mp4,video/mpeg,video/quicktime,video/x-msvideo",
      maxSize: 16 * 1024 * 1024, // 16MB
    },
    audio: {
      size: 16,
      types: "audio/mpeg,audio/wav,audio/ogg,audio/mp3",
      maxSize: 16 * 1024 * 1024, // 16MB
    },
    document: {
      size: 95,
      types: ".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx",
      maxSize: 95 * 1024 * 1024, // 95MB
    },
  };

  const getMediaIcon = (mediaType) => {
    switch (mediaType) {
      case "image":
        return "🖼️";
      case "video":
        return "🎥";
      case "audio":
        return "🎵";
      case "document":
        return "📄";
      default:
        return "📎";
    }
  };

  // Check if caption should be shown (hide for audio)
  const showCaption = selectedMediaType !== "audio";

  return (
    <WrapperStyled>
      <BuilderDrawer
        title={
          <BuilderDrawerTitle
            icon={<span style={{ fontSize: "16px" }}>{getMediaIcon(selectedMediaType)}</span>}
          >
            Configure Media Message
          </BuilderDrawerTitle>
        }
        width={520}
        onClose={onClose}
        open={visible}
        extra={
          <Space>
            <Button onClick={onClose}>Cancel</Button>
            <Button
              type="primary"
              htmlType="submit"
              onClick={() => form.submit()}
              loading={uploading}
            >
              Save Media Message
            </Button>
          </Space>
        }
      >
        <Form
          form={form}
          name="media-message"
          onFinish={onFinish}
          onFinishFailed={onFinishFailed}
          autoComplete="off"
          layout="vertical"
        >
          <Form.Item
            label="Node Label"
            name="label"
            rules={[{ required: true, message: "Please enter a label!" }]}
          >
            <Input placeholder="Enter node label" />
          </Form.Item>

          <Form.Item
            label="Media Type"
            name="mediaType"
            rules={[{ required: true, message: "Please select media type!" }]}
          >
            <Radio.Group
              onChange={(e) => setSelectedMediaType(e.target.value)}
              value={selectedMediaType}
            >
              <Space direction="vertical">
                <Radio value="image">🖼️ Image</Radio>
                <Radio value="video">🎥 Video</Radio>
                <Radio value="audio">🎵 Audio</Radio>
                <Radio value="document">📄 Document</Radio>
              </Space>
            </Radio.Group>
          </Form.Item>

          {/* Conditionally render caption based on media type */}
          {showCaption && (
            <>
              <Form.Item
                label="Caption (Optional)"
                name="caption"
                rules={[
                  {
                    max: 1024,
                    message: "Caption must be less than 1024 characters!",
                  },
                ]}
              >
                <TextArea
                  placeholder="Enter your caption here. You can use variables like {{first_name}}, {{phone_number}}, etc."
                  maxLength={1024}
                  showCount
                  rows={4}
                />
              </Form.Item>

              {/* <Form.Item>
                <VariableHelper
                  onVariableSelect={handleVariableSelect}
                  placeholder="Insert Variable"
                  style={{ width: "100%" }}
                />
              </Form.Item> */}
            </>
          )}

          {/* File Upload - NOW REQUIRED */}
          <Form.Item
            label={`Upload ${selectedMediaType} (Required)`}
            name="mediaFile"
            valuePropName="fileList"
            getValueFromEvent={(e) => (Array.isArray(e) ? e : e && e.fileList)}
            rules={[
              {
                required: true,
                validator: (_, value) => {
                  if (!fileList || fileList.length === 0) {
                    return Promise.reject(
                      new Error(`Please upload a ${selectedMediaType} file`)
                    );
                  }
                  return Promise.resolve();
                },
              },
            ]}
          >
            <Upload
              accept={mediaConstraints[selectedMediaType]?.types}
              beforeUpload={(file) => {
                // Check file size
                const maxSize = mediaConstraints[selectedMediaType]?.maxSize;
                if (file.size > maxSize) {
                  message.error(
                    `File size must be less than ${maxSize / 1024 / 1024}MB`
                  );
                  return false;
                }
                return false; // Prevent auto upload
              }}
              listType="picture"
              onChange={handleFileChange}
              fileList={fileList}
              maxCount={1}
            >
              <Button icon={<UploadOutlined />} loading={uploading}>
                Click to Upload {selectedMediaType}
              </Button>
            </Upload>
          </Form.Item>

          {/* Additional fields for specific media types */}
          {selectedMediaType === "video" && (
            <>
              <Form.Item
                label="Duration (seconds)"
                name="duration"
                rules={[
                  {
                    type: "number",
                    min: 0,
                    message: "Duration must be positive!",
                  },
                ]}
              >
                <InputNumber
                  placeholder="Enter video duration in seconds"
                  style={{ width: "100%" }}
                  min={0}
                />
              </Form.Item>
            </>
          )}

          {selectedMediaType === "document" && (
            <Form.Item label="File Type" name="fileType">
              <Select placeholder="Select file type">
                <Select.Option value="application/pdf">PDF</Select.Option>
                <Select.Option value="application/msword">
                  Word Document
                </Select.Option>
                <Select.Option value="application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                  Word Document (DOCX)
                </Select.Option>
                <Select.Option value="text/plain">Text File</Select.Option>
                <Select.Option value="application/vnd.ms-excel">
                  Excel Spreadsheet
                </Select.Option>
                <Select.Option value="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                  Excel Spreadsheet (XLSX)
                </Select.Option>
                <Select.Option value="application/vnd.ms-powerpoint">
                  PowerPoint Presentation
                </Select.Option>
                <Select.Option value="application/vnd.openxmlformats-officedocument.presentationml.presentation">
                  PowerPoint Presentation (PPTX)
                </Select.Option>
              </Select>
            </Form.Item>
          )}

          <div
            style={{
              marginTop: "20px",
              padding: "12px",
              background: "#f6ffed",
              border: "1px solid #b7eb8f",
              borderRadius: "6px",
            }}
          >
            <div
              style={{
                fontWeight: "bold",
                marginBottom: "8px",
                color: "#389e0d",
              }}
            >
              📋 Media Constraints
            </div>
            <div style={{ fontSize: "12px", color: "#666" }}>
              <div>
                <strong>Image:</strong> Max 5MB (JPEG, PNG, GIF, WebP)
              </div>
              <div>
                <strong>Video:</strong> Max 16MB (MP4, MPEG, QuickTime, AVI)
              </div>
              <div>
                <strong>Audio:</strong> Max 16MB (MP3, WAV, OGG) - No caption
                supported
              </div>
              <div>
                <strong>Document:</strong> Max 95MB (PDF, DOC, DOCX, TXT, XLS,
                XLSX, PPT, PPTX)
              </div>
              <div
                style={{
                  marginTop: "8px",
                  fontWeight: "bold",
                  color: "#d46b08",
                }}
              >
                ⚠️ File upload is required for all media types
              </div>
            </div>
          </div>
        </Form>
      </BuilderDrawer>
    </WrapperStyled>
  );
};

export default ReactFlowMediaModule;
