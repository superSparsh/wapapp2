import React, { useState, useEffect, useRef } from "react";
import axios from "axios";
import BuilderDrawer from "./BuilderDrawer.jsx";
import {
  Form,
  Card,
  Button,
  Space,
  Select,
  Typography,
  message,
  Radio,
  Divider,
  Tag,
  Tooltip,
  Input,
  Row,
  Col,
  List,
  Modal,
  Switch,
  Spin,
  Upload,
} from "antd";
import {
  MessageOutlined,
  SendOutlined,
  PlusOutlined,
  DeleteOutlined,
  LinkOutlined,
  EnvironmentOutlined,
  ShoppingOutlined,
  AppstoreOutlined,
  PhoneOutlined,
  EditOutlined,
  CopyOutlined,
  UploadOutlined,
} from "@ant-design/icons";
import VariableHelper from "./VariableHelper.jsx";
import InteractiveMessagePreview from "./InteractiveMessagePreview";

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const { Option } = Select;

const ReactFlowInteractiveMessageModule = ({
  visible,
  onClose,
  onSave,
  nodeData = null,
  variables = [],
  isNodeFlow = true,
  name = "",
  interactiveMessages = [],
}) => {
  const [form] = Form.useForm();
  // Watch all form values for the preview
  const formValues = Form.useWatch([], form);

  const [loading, setLoading] = useState(false);
  const [interactiveType, setInteractiveType] = useState("list");
  const [sections, setSections] = useState([{ rows: [] }]);
  const [buttons, setButtons] = useState([]);
  const [products, setProducts] = useState([]);
  const [showAddProductModal, setShowAddProductModal] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);
  const [catalogs, setCatalogs] = useState([]);
  const [loadingCatalogs, setLoadingCatalogs] = useState(false);
  const [loadingProducts, setLoadingProducts] = useState(false);
  const [selectedCatalog, setSelectedCatalog] = useState(null);
  const [flows, setFlows] = useState([]);
  const [loadingFlows, setLoadingFlows] = useState(false);
  const [fetchingFlowJson, setFetchingFlowJson] = useState(false);
  const [selectedFlowData, setSelectedFlowData] = useState(null);
  const [sectionValidationAttempted, setSectionValidationAttempted] = useState(false);

  // New state for button message header and media
  const [headerType, setHeaderType] = useState("none");
  const [uploadedMedia, setUploadedMedia] = useState(null);
  const [fileUploadLoader, setFileUploadLoader] = useState(false);
  const [mediaModalOpen, setMediaModalOpen] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);

  // Timeout configuration state
  const [timeoutConfig, setTimeoutConfig] = useState({
    unreadTimeout: 300, // 5 minutes default
    undeliveredTimeout: 60, // 1 minute default
  });

  // Ref to track if we've set form values for editing
  const hasSetFormValues = React.useRef(false);
  const [formValuesToSet, setFormValuesToSet] = useState(null);

  // Initialize form with node data if editing
  useEffect(() => {
    if (!visible) {
      hasSetFormValues.current = false;
      setSectionValidationAttempted(false);
      return;
    }

    if (visible && nodeData) {
      console.log(
        "Editing existing interactive message node with data:",
        nodeData
      );

      // Set timeout config first
      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }

      // Set interactive type first
      if (nodeData.interactiveType) {
        setInteractiveType(nodeData.interactiveType);
      }

      // Set header type and media for button messages
      if (nodeData.interactiveType === "button") {
        if (nodeData.headerType) {
          setHeaderType(nodeData.headerType);
        }
        if (nodeData.uploadedMedia) {
          setUploadedMedia(nodeData.uploadedMedia);
        }
      }

      // Set sections, buttons, products
      if (nodeData.sections) {
        setSections(nodeData.sections);
      }
      if (nodeData.buttons) {
        setButtons(nodeData.buttons);
      }
      if (nodeData.products) {
        setProducts(nodeData.products);
      }
      if (nodeData.catalog_id) {
        setSelectedCatalog(nodeData.catalog_id);
      }
      if (nodeData.flow_id) {
        setSelectedFlowData(nodeData.selectedFlowData);
      }

      // Store the form values to be set after interactive type is updated
      const formValues = {
        ...nodeData,
        name: nodeData.name || name || "",
        unreadTimeout: nodeData.timeoutConfig?.unreadTimeout || 300,
        undeliveredTimeout: nodeData.timeoutConfig?.undeliveredTimeout || 60,
      };

      console.log("Setting interactive message form values:", formValues);
      console.log("Header text from nodeData:", nodeData.headerText);
      console.log("Body text from nodeData:", nodeData.bodyText);
      console.log("Footer text from nodeData:", nodeData.footerText);

      // Store form values to be set after interactive type is updated
      setFormValuesToSet(formValues);
    } else if (visible) {
      console.log("Creating new interactive message node - resetting form");
      // Reset all form fields and state for new node
      hasSetFormValues.current = false;
      setFormValuesToSet(null);
      form.resetFields();
      setInteractiveType("list");
      setSections([{ rows: [], product_items: [] }]);
      setButtons([]);
      setProducts([]);
      setSelectedCatalog(null);
      setShowAddProductModal(false);
      setEditingProduct(null);
      setSelectedFlowData(null);
      setHeaderType("none");
      setUploadedMedia(null);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });
      form.setFieldsValue({
        name: name || "",
        interactiveType: "list",
        headerText: "",
        bodyText: "",
        footerText: "",
        buttonText: "",
        sections: [],
        buttons: [],
        products: [],
        url: "",
        flow_id: "",
        flow_token: "",
        flow_cta: "",
        flow_action_payload: "",
        productId: "",
        catalog_id: "",
        unreadTimeout: 300,
        undeliveredTimeout: 60,
        headerType: "none",
      });
    }
  }, [visible, nodeData, form]);

  // Set form values after interactive type is updated (for editing existing nodes)
  useEffect(() => {
    if (
      visible &&
      formValuesToSet &&
      interactiveType &&
      formValuesToSet.interactiveType === interactiveType &&
      !hasSetFormValues.current
    ) {
      console.log(
        "Interactive type updated, setting form values for:",
        interactiveType
      );

      // Set form values with a slight delay to ensure fields are rendered
      setTimeout(() => {
        form.setFieldsValue(formValuesToSet);
        hasSetFormValues.current = true;
        setFormValuesToSet(null); // Clear the stored values
        console.log("Form values set after delay");
      }, 100);

      console.log("Form values set immediately:");
      console.log("Current headerText:", form.getFieldValue("headerText"));
      console.log("Current bodyText:", form.getFieldValue("bodyText"));
      console.log("Current footerText:", form.getFieldValue("footerText"));
    }
  }, [visible, formValuesToSet, interactiveType, form]);

  // Fetch catalogs on component mount
  useEffect(() => {
    if (visible) {
      fetchCatalogs();
    }
  }, [visible]);

  // Fetch flows on component mount
  useEffect(() => {
    if (visible) {
      fetchFlows();
    }
  }, [visible]);

  // Fetch products when catalog is selected
  useEffect(() => {
    if (selectedCatalog) {
      fetchProducts(selectedCatalog);
    }
  }, [selectedCatalog]);

  const fetchCatalogs = async () => {
    try {
      setLoadingCatalogs(true);
      const response = await axios.get("/getCatalogData");
      if (response.data.success) {
        setCatalogs(response.data.catalogs);
      } else {
        message.error(response.data.message);
      }
    } catch (error) {
      message.error("Failed to fetch catalogs");
    } finally {
      setLoadingCatalogs(false);
    }
  };

  const fetchProducts = async (catalogId) => {
    try {
      setLoadingProducts(true);
      const response = await axios.get("/getProductData", {
        params: { catalogId },
      });
      if (response.data.success) {
        setProducts(response.data.products);
      } else {
        message.error(response.data.message);
      }
    } catch (error) {
      message.error("Failed to fetch products");
    } finally {
      setLoadingProducts(false);
    }
  };

  const handleCatalogChange = (value) => {
    setSelectedCatalog(value);
    form.setFieldsValue({ product_retailer_id: undefined }); // Reset product selection

    // Reset sections when catalog changes for product_list
    if (interactiveType === "product_list") {
      setSections([{ title: "", product_items: [] }]);
    }
  };

  // Flow Management Functions (matching inbox implementation)
  const generateFlowToken = (flowId) => {
    return `${flowId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  };

  const fetchFlows = async () => {
    try {
      const csrfToken = localStorage.getItem("csrfToken");
      setLoadingFlows(true);
      const response = await axios.get("/getflowData", {
        headers: {
          "X-CSRF-TOKEN": csrfToken,
        },
      });

      if (response.data.status && response.data.flow_data?.data) {
        setFlows(response.data.flow_data.data);
      } else {
        message.error("Failed to fetch flows");
      }
    } catch (error) {
      console.error("Error loading flows:", error);
      message.error("Error loading flows");
    } finally {
      setLoadingFlows(false);
    }
  };

  const fetchFlowJson = async (flowId) => {
    try {
      setFetchingFlowJson(true);
      const csrfToken = localStorage.getItem("csrfToken");

      const response = await axios.get(`/getflowJsonCode/${flowId}`, {
        headers: {
          "X-CSRF-TOKEN": csrfToken,
        },
      });

      if (response.data.status && response.data.flow_data) {
        const parsedData = response.data.flow_data;

        if (parsedData?.screens?.length > 0) {
          setSelectedFlowData(parsedData);
          const firstScreenId = parsedData.screens[0].id;
          form.setFieldsValue({
            flow_token: generateFlowToken(flowId),
            flow_action_payload: JSON.stringify(
              {
                screen: firstScreenId,
              },
              null,
              2
            ),
          });
        } else {
          message.error("Selected flow has no valid screens");
        }
      } else {
        message.error(response.data.message || "Flow data not available");
      }
    } catch (error) {
      console.error("Error fetching flow JSON:", error);
      const errorMsg =
        error.response?.data?.message ||
        error.response?.data?.error ||
        "Error fetching flow details. Please try again.";
      message.error(errorMsg);
    } finally {
      setFetchingFlowJson(false);
    }
  };

  const handleFlowChange = async (flowId) => {
    await fetchFlowJson(flowId);
  };

  // File upload function to backend
  const sendFileToBackend = async (file) => {
    try {
      setFileUploadLoader(true);

      // Get CSRF token from local storage
      const csrfToken = localStorage.getItem("csrfToken");
      const url = window.location.origin + "/inbox/fileuploadinpublic";

      const formData = new FormData();
      formData.append("file", file);

      const response = await axios.post(url, formData, {
        headers: {
          "X-CSRF-TOKEN": csrfToken,
          "Content-Type": "multipart/form-data",
        },
      });

      if (response.data) {
        if (response.data.message === "Media Send successfully") {
          // Construct full URL from the path
          const baseUrl = window.location.origin;
          const fullUrl = `${baseUrl}/${response.data.path}`;

          setUploadedMedia({
            type: "backend",
            fullUrl: fullUrl,
            path: response.data.path,
            filename: response.data.file_name,
            msg_type: response.data.msg_type,
            file_type: response.data.file_type,
            file_size: response.data.file_size,
            thumbnail_path: response.data.thumbnail_path,
            duration: response.data.duration,
          });

          message.success("Media uploaded successfully!");
          setMediaModalOpen(false);
        } else if (response.data.error) {
          message.error(response.data.error);
        }
      }
    } catch (error) {
      console.error("Error uploading file:", error);
      if (error.response) {
        message.error(error.response.data.error || "File upload failed");
      } else {
        message.error("File upload failed");
      }
    } finally {
      setFileUploadLoader(false);
    }
  };

  const handleMediaUpload = (file, type) => {
    setSelectedFile(file);
    setMediaModalOpen(true);
    return false; // Prevent default upload behavior
  };

  const handleExternalMedia = (values) => {
    setUploadedMedia({
      type: "link",
      link: values.link,
      ...(headerType === "document" && { filename: values.filename }),
    });
    message.success("External media configured");
  };

  const confirmMediaUpload = () => {
    if (selectedFile) {
      sendFileToBackend(selectedFile);
    }
  };

  const cancelMediaUpload = () => {
    setMediaModalOpen(false);
    setSelectedFile(null);
  };

  const getFileType = (headerType) => {
    switch (headerType) {
      case "image":
        return "image/*";
      case "video":
        return "video/*";
      case "document":
        return "*/*";
      default:
        return "*/*";
    }
  };

  const renderHeaderFields = () => {
    if (headerType === "none") return null;

    if (headerType === "text") {
      return (
        <Form.Item label="Header Text" required>
          <Form.Item
            name="headerText"
            noStyle
            rules={[{ required: true, message: "Header text is required" }]}
          >
            <Input placeholder="Enter header text" maxLength={60} showCount />
          </Form.Item>
          <div style={{ marginTop: 8 }}>
            <VariableHelper
              variables={variables}
              onVariableSelect={(syntax) =>
                handleVariableSelect(syntax, "headerText")
              }
              placeholder="Insert Variable"
              size="small"
            />
          </div>
        </Form.Item>
      );
    }

    // For media types (image, video, document)
    return (
      <Space direction="vertical" style={{ width: "100%" }}>
        <div>
          <Upload
            accept={getFileType(headerType)}
            beforeUpload={(file) => handleMediaUpload(file, headerType)}
            showUploadList={false}
          >
            <Button icon={<UploadOutlined />}>Upload {headerType}</Button>
          </Upload>

          <span style={{ marginLeft: 8, color: "#666" }}>
            Or use external link:
          </span>
        </div>

        <Form.Item
          label={`External ${headerType} Link`}
          name={`${headerType}Link`}
        >
          <Input
            placeholder={`Enter ${headerType} URL`}
            onChange={(e) => {
              if (e.target.value) {
                handleExternalMedia({
                  link: e.target.value,
                  ...(headerType === "document" && {
                    filename: form.getFieldValue("documentFilename"),
                  }),
                });
              }
            }}
          />
        </Form.Item>

        {headerType === "document" && (
          <Form.Item label="Document Filename" name="documentFilename">
            <Input placeholder="Enter filename" />
          </Form.Item>
        )}

        {uploadedMedia && (
          <div style={{ padding: 8, background: "#f5f5f5", borderRadius: 6 }}>
            <Space direction="vertical" style={{ width: "100%" }}>
              <div>
                <strong>Media Status:</strong>{" "}
                {uploadedMedia.type === "id"
                  ? `Uploaded to WhatsApp: ${uploadedMedia.id}`
                  : uploadedMedia.type === "link"
                    ? `External: ${uploadedMedia.link}`
                    : `Uploaded to Backend: ${uploadedMedia.filename}`}
              </div>
              {uploadedMedia.type === "backend" && (
                <div>
                  <strong>URL:</strong> {uploadedMedia.fullUrl}
                </div>
              )}
              <Button
                type="text"
                danger
                size="small"
                icon={<DeleteOutlined />}
                onClick={() => setUploadedMedia(null)}
              >
                Remove Media
              </Button>
            </Space>
          </div>
        )}
      </Space>
    );
  };

  const handleMessageSelect = (messageId) => {
    if (!messageId) {
      form.resetFields();
      setSections([{ rows: [], product_items: [] }]);
      setButtons([]);
      setProducts([]);
      setHeaderType("none");
      setUploadedMedia(null);
      return;
    }

    const selectedMessage = interactiveMessages.find((m) => m.id === messageId);
    if (selectedMessage) {
      let data = selectedMessage.content;

      // If content is already an object (due to Laravel array casting), use it directly.
      // Otherwise, try to parse it.
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch (error) {
          console.error("Error parsing message content:", error);
          message.error("Failed to load message content");
          return;
        }
      }

      if (data && typeof data === 'object') {
        // Log for debugging
        console.log("Loading selected message data:", data);

        const iType = data.interactiveType || data.type || "list";

        // Update states first so sub-forms render correctly
        setInteractiveType(iType);

        if (data.sections) setSections(data.sections);
        if (data.buttons) setButtons(data.buttons);
        if (data.products) setProducts(data.products);
        if (data.headerType) setHeaderType(data.headerType);
        if (data.uploadedMedia) setUploadedMedia(data.uploadedMedia);
        if (data.catalog_id) setSelectedCatalog(data.catalog_id);

        // Update form fields
        form.setFieldsValue({
          ...data,
          interactiveType: iType,
          name: selectedMessage.name,
        });
      }
    }
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      // Validate sections and rows
      if (interactiveType === "list") {
        const hasValidRows = sections.some((s) => s.rows.length > 0);
        if (!hasValidRows) {
          message.error("Please add at least one row to a section");
          setLoading(false);
          return;
        }
        const hasEmptyTitles = sections.some((section) => !section.title?.trim());
        if (hasEmptyTitles) {
          setSectionValidationAttempted(true);
          message.error("Please provide titles for all sections");
          setLoading(false);
          return;
        }
      }

      // Validate buttons
      if (interactiveType === "button") {
        if (buttons.length === 0) {
          message.error("At least one button is required");
          setLoading(false);
          return;
        }
      }

      // Validate product_list
      if (interactiveType === "product_list") {
        const hasProducts = sections.some(
          (s) => (s.product_items || []).length > 0
        );
        if (!hasProducts) {
          message.error("At least one product is required");
          setLoading(false);
          return;
        }
        const hasEmptyProducts = sections.some((section) =>
          (section.product_items || []).some(
            (item) => !item.product_retailer_id
          )
        );
        if (hasEmptyProducts) {
          message.error("Please select products for all items");
          setLoading(false);
          return;
        }
        const hasEmptyTitles = sections.some((section) => !section.title);
        if (hasEmptyTitles) {
          setSectionValidationAttempted(true);
          message.error("Please provide titles for all sections");
          setLoading(false);
          return;
        }
      }

      // Validate CTA URL
      if (interactiveType === "cta_url") {
        // values.buttonText and values.url checked by form validation usually, but double check
        if (!values.buttonText || !values.url) {
          message.error("Button text and URL are required");
          setLoading(false);
          return;
        }
      }

      // Validate Flow
      if (interactiveType === "flow") {
        if (!selectedFlowData?.screens?.length) {
          message.error("Please select a valid flow with screens first");
          setLoading(false);
          return;
        }
      }

      // Construct the specific payload structure for the backend
      let contentPayload = {
        type: interactiveType
      };

      // Header (Common for list, button, product_list)
      // Note: product doesn't seem to have header in the form usually, but check requirements.
      // Based on form:
      if (["list", "button", "product_list"].includes(interactiveType)) {
        // For list and product_list, header is text only in this form version?
        // 'list' form has headerText.
        // 'button' has headerType.

        if (interactiveType === "button") {
          if (headerType !== "none") {
            if (headerType === "text" && values.headerText) {
              contentPayload.header = { type: "text", text: values.headerText };
            } else if (uploadedMedia) {
              // Media header construction
              let mediaObj = {};
              if (uploadedMedia.type === "id") {
                mediaObj.id = uploadedMedia.id;
              } else if (uploadedMedia.type === "link") {
                mediaObj.link = uploadedMedia.link;
              } else if (uploadedMedia.type === "backend") {
                mediaObj.link = uploadedMedia.fullUrl;
              }

              if (headerType === "document" && uploadedMedia.filename) {
                mediaObj.filename = uploadedMedia.filename;
              }

              contentPayload.header = {
                type: headerType,
                [headerType]: mediaObj
              };
            }
          }
        } else {
          // List and Product List usually support text header in this UI
          if (values.headerText) {
            contentPayload.header = { type: "text", text: values.headerText };
          }
        }
      }

      // Body (Common)
      if (values.bodyText) {
        contentPayload.body = { text: values.bodyText };
      }

      // Footer (Common)
      if (values.footerText) {
        contentPayload.footer = { text: values.footerText };
      }

      // Action Construction based on Type
      if (interactiveType === "list") {
        contentPayload.action = {
          button: values.buttonText || "Menu",
          sections: sections.map(s => ({
            title: s.title,
            rows: s.rows.map(r => ({
              id: r.id,
              title: r.title,
              description: r.description
            }))
          }))
        };
      } else if (interactiveType === "button") {
        contentPayload.action = {
          buttons: buttons.map(b => ({
            type: "reply",
            reply: {
              id: b.id,
              title: b.title
            }
          }))
        };
      } else if (interactiveType === "product") {
        // Single product
        contentPayload.type = "product"; // Backend expects 'product'
        // Body is optional now in backend, but if provided include it.
        contentPayload.action = {
          catalog_id: selectedCatalog, // or values.catalog_id if in form
          product_retailer_id: values.product_retailer_id
        };
      } else if (interactiveType === "product_list") {
        contentPayload.action = {
          catalog_id: selectedCatalog,
          sections: sections.map(s => ({
            title: s.title,
            product_items: s.product_items.map(p => ({
              product_retailer_id: p.product_retailer_id
            }))
          }))
        };
      } else if (interactiveType === "cta_url") {
        contentPayload.action = {
          name: "cta_url",
          parameters: {
            display_text: values.buttonText,
            url: values.url
          }
        };
      } else if (interactiveType === "flow") {
        contentPayload.action = {
          name: "flow",
          parameters: {
            mode: "published",
            flow_message_version: "3",
            flow_token: values.flow_token,
            flow_id: values.flow_id,
            flow_cta: values.flow_cta,
            flow_action: "navigate",
            flow_action_payload: JSON.parse(values.flow_action_payload || "{}")
          }
        };
      } else if (interactiveType === "location_request_message") {
        contentPayload.type = "location_request_message";
        contentPayload.action = { name: "send_location" };
      } else if (interactiveType === "catalog_message") {
        contentPayload.type = "catalog_message";
        contentPayload.action = {
          name: "catalog_message",
          catalog_id: values.catalog_id || selectedCatalog
        };
      } else if (interactiveType === "address_message") {
        contentPayload.type = "address_message";
        contentPayload.action = {
          name: "address_message",
          parameters: { country: "IN" }
        };
      }

      // Preserve UI-specific state in the content for editing later
      // We can mix the API payload with UI state, or keep them separate.
      // Index.jsx saves 'nodeData' as 'content'.
      // If we change structure, we must ensure 'handleEdit' in ReactFlow can map it back.
      // But for SENDING, we need the structure we just built.

      // Strategy: Save the 'API Ready' structure as standard keys, 
      // AND KEEP the flattened form values for the UI to be able to reload the form.
      // The backend 'sendInteractive' uses 'content' directly.
      // So 'content' MUST conform to API structure.
      // But 'InteractiveMessageIndex' uses 'content' to populate 'nodeData'.
      // We need to merge them.

      // Wait, if we change 'content' structure to be nested ({ type, body, action }), 
      // then 'ReactFlowInteractiveMessageModule.jsx's 'useEffect' for editing (lines 87-191) 
      // needs to be able to read that nested structure back into form fields.

      // CURRENTLY, the useEffect reads flattened fields: nodeData.headerText, nodeData.interactiveType.
      // If we save nested structure, we break editing unless we update the useEffect too.

      // COMPROMISE: Save the object with BOTH format or strictly nested format and update helper to parse.
      // Or, easier: Just construct the payload in 'SendTemplateComponent' if possible? 
      // No, 'SendTemplateComponent' just takes 'content' from DB.

      // Better: In `handleSave`, we return a merged object.
      // The `content` column in DB is JSON.
      // `InboxService` extracts `type` and `action` from it.
      // If we add `action` and `header`/`body` objects to the flattened `nodeData`, 
      // `InboxService` will find `content['action']` and function correctly.
      // AND `ReactFlowInteractiveMessageModule` will still find `nodeData.headerText`.

      // So, I will ADD the strict structure keys to the `nodeData` object without removing the flat keys.

      const nodeData = {
        ...values,
        interactiveType: interactiveType,
        sections: sections,
        buttons: buttons,
        products: products,
        // UI specific
        headerType: headerType,
        uploadedMedia: uploadedMedia,
        timeoutConfig: {
          unreadTimeout: timeoutConfig.unreadTimeout,
          undeliveredTimeout: timeoutConfig.undeliveredTimeout,
        },
        label: values.name || `Interactive Message - ${getInteractiveTypeLabel(interactiveType)}`,

        // API STRUCTURE (InboxService requirements)
        // We override or add the nested keys expected by backend
        type: contentPayload.type,
        header: contentPayload.header,
        body: contentPayload.body,
        footer: contentPayload.footer,
        action: contentPayload.action
      };

      console.log("handleSave - final nodeData:", nodeData);

      onSave(nodeData);
      message.success("Interactive message configuration saved successfully!");
      onClose();
    } catch (error) {
      console.error("Form validation failed:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleVariableSelect = (variableSyntax, fieldName) => {
    const currentValue = form.getFieldValue(fieldName) || "";
    form.setFieldsValue({ [fieldName]: currentValue + variableSyntax });
  };

  const getInteractiveTypeLabel = (type) => {
    const labels = {
      list: "List Message",
      button: "Buttons Message",
      product: "Single Product",
      product_list: "Multi-Product",
      catalog_message: "Product Catalog",
      cta_url: "CTA Button",
      flow: "WhatsApp Flow",
      location_request_message: "Location Request",
      address_message: "Address Message",
    };
    return labels[type] || "Interactive Message";
  };

  const getInteractiveTypeIcon = (type) => {
    const icons = {
      list: <AppstoreOutlined />,
      button: <MessageOutlined />,
      product: <ShoppingOutlined />,
      product_list: <ShoppingOutlined />,
      catalog_message: <ShoppingOutlined />,
      cta_url: <LinkOutlined />,
      flow: <MessageOutlined />,
      location_request_message: <EnvironmentOutlined />,
      address_message: <EnvironmentOutlined />,
    };
    return icons[type] || <MessageOutlined />;
  };

  // Section Management for List Messages (matching inbox implementation)
  const handleAddSection = () => {
    if (interactiveType === "product_list") {
      setSections([...sections, { title: "", product_items: [] }]);
    } else {
      setSections([...sections, { title: "", rows: [] }]);
    }
  };

  const handleDeleteSection = (index) => {
    const updatedSections = sections.filter((_, i) => i !== index);
    setSections(updatedSections);
  };

  const handleUpdateSectionTitle = (sectionIndex, value) => {
    const newSections = [...sections];
    newSections[sectionIndex].title = value;
    setSections(newSections);
  };

  const handleAddRow = (sectionIndex) => {
    const newSections = [...sections];
    newSections[sectionIndex].rows.push({
      id: `row_${Date.now()}`,
      title: "",
      description: "",
    });
    setSections(newSections);
  };

  const handleDeleteRow = (sectionIndex, rowIndex) => {
    const newSections = [...sections];
    newSections[sectionIndex].rows.splice(rowIndex, 1);
    setSections(newSections);
  };

  const handleUpdateRowField = (sectionIndex, rowIndex, field, value) => {
    const newSections = [...sections];
    newSections[sectionIndex].rows[rowIndex][field] = value;
    setSections(newSections);
  };

  // Button Management for Button Messages (matching inbox implementation)
  const handleAddButton = () => {
    if (buttons.length >= 3) {
      message.warning("Maximum 3 buttons allowed");
      return;
    }
    const newButton = {
      id: `btn_${Date.now()}`,
      title: "",
    };
    setButtons([...buttons, newButton]);
  };

  const handleDeleteButton = (index) => {
    const newButtons = [...buttons];
    newButtons.splice(index, 1);
    setButtons(newButtons);
  };

  const handleUpdateButtonField = (index, field, value) => {
    const newButtons = [...buttons];
    newButtons[index][field] = value;
    setButtons(newButtons);
  };

  // Product Management
  const handleAddProduct = (productData) => {
    if (editingProduct !== null) {
      const updatedProducts = products.map((product, index) =>
        index === editingProduct ? { ...productData, id: product.id } : product
      );
      setProducts(updatedProducts);
      setEditingProduct(null);
    } else {
      const newProduct = {
        ...productData,
        id: `product_${Date.now()}`,
      };
      setProducts([...products, newProduct]);
    }
    setShowAddProductModal(false);
    form.resetFields([
      "productId",
      "productName",
      "productDescription",
      "productPrice",
    ]);
  };

  const handleEditProduct = (index) => {
    const product = products[index];
    setEditingProduct(index);
    form.setFieldsValue({
      productId: product.id,
      productName: product.name,
      productDescription: product.description,
      productPrice: product.price,
    });
    setShowAddProductModal(true);
  };

  const handleDeleteProduct = (index) => {
    const updatedProducts = products.filter((_, i) => i !== index);
    setProducts(updatedProducts);
  };

  // Multi-Product Management Functions (matching inbox implementation)
  const handleProductChange = (value, sectionIdx, itemIdx) => {
    if (!value) return;

    const newSections = [...sections];

    // Check if product already exists in this section only
    const isDuplicateInSection = newSections[sectionIdx].product_items?.some(
      (item, idx) => item.product_retailer_id === value && idx !== itemIdx
    );

    if (isDuplicateInSection) {
      message.error("This product is already in this section");
      return;
    }

    if (!newSections[sectionIdx].product_items) {
      newSections[sectionIdx].product_items = [];
    }
    newSections[sectionIdx].product_items[itemIdx].product_retailer_id = value;
    setSections(newSections);
  };

  const handleAddProductToSection = (sectionIdx) => {
    const newSections = [...sections];
    if (!newSections[sectionIdx].product_items) {
      newSections[sectionIdx].product_items = [];
    }
    newSections[sectionIdx].product_items.push({ product_retailer_id: "" });
    setSections(newSections);
  };

  const handleRemoveProduct = (sectionIdx, itemIdx) => {
    const newSections = [...sections];
    newSections[sectionIdx].product_items.splice(itemIdx, 1);
    setSections(newSections);
  };

  const renderListMessageForm = () => {
    console.log(
      "renderListMessageForm called, interactiveType:",
      interactiveType
    );
    return (
      <Card
        title="List Message Configuration"
        size="small"
        style={{ marginBottom: 16 }}
      >
        <Form.Item label="Header Text" required>
          <Form.Item
            name="headerText"
            noStyle
            rules={[{ required: true, message: "Please enter header text" }]}
          >
            <TextArea
              rows={2}
              placeholder="Enter header text for the list message..."
              maxLength={60}
              showCount
            />
          </Form.Item>
          <div style={{ marginTop: 8 }}>
            <VariableHelper
              variables={variables}
              onVariableSelect={(syntax) =>
                handleVariableSelect(syntax, "headerText")
              }
              placeholder="Insert Variable"
              size="small"
            />
          </div>
        </Form.Item>

        <Form.Item label="Body Text" required>
          <Form.Item
            name="bodyText"
            noStyle
            rules={[{ required: true, message: "Please enter body text" }]}
          >
            <TextArea
              rows={3}
              placeholder="Enter body text for the list message..."
              maxLength={1024}
              showCount
            />
          </Form.Item>
          <div style={{ marginTop: 8 }}>
            <VariableHelper
              variables={variables}
              onVariableSelect={(syntax) =>
                handleVariableSelect(syntax, "bodyText")
              }
              placeholder="Insert Variable"
              size="small"
            />
          </div>
        </Form.Item>

        <Form.Item label="Footer Text">
          <Form.Item name="footerText" noStyle>
            <TextArea
              rows={2}
              placeholder="Enter footer text (optional)..."
              maxLength={60}
              showCount
            />
          </Form.Item>
          <div style={{ marginTop: 8 }}>
            <VariableHelper
              variables={variables}
              onVariableSelect={(syntax) =>
                handleVariableSelect(syntax, "footerText")
              }
              placeholder="Insert Variable"
              size="small"
            />
          </div>
        </Form.Item>

        <Form.Item
          name="buttonText"
          label="Button Text"
          rules={[{ required: true, message: "Please enter button text" }]}
        >
          <Input placeholder="e.g., View Options, Select Item" maxLength={20} />
        </Form.Item>

        <Divider orientation="left">Sections</Divider>
        {sections.map((section, sectionIndex) => (
          <div
            key={sectionIndex}
            style={{
              marginBottom: 16,
              padding: 16,
              border: "1px solid #f0f0f0",
            }}
          >
            <Form.Item
              label={`Section ${sectionIndex + 1} Title`}
              required
              validateStatus={
                sectionValidationAttempted && !section.title?.trim() ? "error" : ""
              }
              help={
                sectionValidationAttempted && !section.title?.trim()
                  ? "Section title is required"
                  : null
              }
            >
              <div>
                <Input
                  value={section.title}
                  onChange={(e) =>
                    handleUpdateSectionTitle(sectionIndex, e.target.value)
                  }
                  placeholder="Section title (max 24 chars)"
                  maxLength={24}
                />
                <div style={{ marginTop: 8 }}>
                  <VariableHelper
                    variables={variables}
                    onVariableSelect={(syntax) => {
                      const currentValue = section.title || "";
                      const newValue = currentValue + syntax;
                      handleUpdateSectionTitle(sectionIndex, newValue);
                    }}
                    placeholder="Insert Variable"
                    size="small"
                  />
                </div>
              </div>
            </Form.Item>

            <List
              header={<div>Rows (1-10 per section)</div>}
              dataSource={section.rows}
              renderItem={(row, rowIndex) => (
                <List.Item>
                  <Space direction="vertical" style={{ width: "100%" }}>
                    <Input
                      placeholder="Row ID (required)"
                      value={row.id}
                      onChange={(e) =>
                        handleUpdateRowField(
                          sectionIndex,
                          rowIndex,
                          "id",
                          e.target.value
                        )
                      }
                      status={!row.id ? "error" : ""}
                    />
                    <div>
                      <Input
                        placeholder="Row title (max 24 chars)"
                        value={row.title}
                        onChange={(e) =>
                          handleUpdateRowField(
                            sectionIndex,
                            rowIndex,
                            "title",
                            e.target.value
                          )
                        }
                        maxLength={24}
                        status={!row.title ? "error" : ""}
                      />
                      <div style={{ marginTop: 8 }}>
                        <VariableHelper
                          variables={variables}
                          onVariableSelect={(syntax) => {
                            const currentValue = row.title || "";
                            const newValue = currentValue + syntax;
                            handleUpdateRowField(
                              sectionIndex,
                              rowIndex,
                              "title",
                              newValue
                            );
                          }}
                          placeholder="Insert Variable"
                          size="small"
                        />
                      </div>
                    </div>
                    <div>
                      <Input.TextArea
                        placeholder="Row description (optional, max 72 chars)"
                        value={row.description}
                        onChange={(e) =>
                          handleUpdateRowField(
                            sectionIndex,
                            rowIndex,
                            "description",
                            e.target.value
                          )
                        }
                        maxLength={72}
                        rows={2}
                      />
                      <div style={{ marginTop: 8 }}>
                        <VariableHelper
                          variables={variables}
                          onVariableSelect={(syntax) => {
                            const currentValue = row.description || "";
                            const newValue = currentValue + syntax;
                            handleUpdateRowField(
                              sectionIndex,
                              rowIndex,
                              "description",
                              newValue
                            );
                          }}
                          placeholder="Insert Variable"
                          size="small"
                        />
                      </div>
                    </div>
                    <Button
                      danger
                      onClick={() => handleDeleteRow(sectionIndex, rowIndex)}
                    >
                      Remove Row
                    </Button>
                  </Space>
                </List.Item>
              )}
            />
            <Button
              type="dashed"
              onClick={() => handleAddRow(sectionIndex)}
              block
              disabled={section.rows.length >= 10}
            >
              Add Row
            </Button>
          </div>
        ))}

        <Button
          type="dashed"
          onClick={handleAddSection}
          block
          disabled={sections.length >= 10}
        >
          Add Section
        </Button>
      </Card>
    );
  };

  const renderButtonsMessageForm = () => (
    <Card
      title="Buttons Message Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      {/* Header Type Selection */}
      <Form.Item label="Header Type" name="headerType" initialValue="none">
        <Select onChange={setHeaderType}>
          <Option value="none">No Header</Option>
          <Option value="text">Text Header</Option>
          <Option value="image">Image Header</Option>
          <Option value="video">Video Header</Option>
          <Option value="document">Document Header</Option>
        </Select>
      </Form.Item>

      {/* Header Fields */}
      {renderHeaderFields()}

      <Form.Item label="Message Body" required>
        <Form.Item
          name="bodyText"
          noStyle
          rules={[{ required: true, message: "Body text is required" }]}
        >
          <TextArea
            rows={3}
            placeholder="Main message text"
            maxLength={1024}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "bodyText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item label="Footer Text (Optional)">
        <Form.Item name="footerText" noStyle>
          <TextArea
            rows={2}
            placeholder="Footer text"
            maxLength={60}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "footerText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item label="Buttons">
        <div style={{ marginBottom: 16 }}>
          <Text type="secondary" style={{ fontSize: "12px", marginBottom: 8 }}>
            Buttons (1-3 allowed)
          </Text>
        </div>

        <List
          header={<div>Buttons (1-3 allowed)</div>}
          dataSource={buttons}
          renderItem={(button, index) => (
            <List.Item>
              <Space direction="vertical" style={{ width: "100%" }}>
                <Input
                  placeholder="Button ID"
                  value={button.id}
                  onChange={(e) =>
                    handleUpdateButtonField(index, "id", e.target.value)
                  }
                  status={!button.id ? "error" : ""}
                />
                <div>
                  <Input
                    placeholder="Button text (max 20 chars)"
                    value={button.title}
                    onChange={(e) =>
                      handleUpdateButtonField(index, "title", e.target.value)
                    }
                    maxLength={20}
                    status={!button.title ? "error" : ""}
                  />
                  <div style={{ marginTop: 8 }}>
                    <VariableHelper
                      variables={variables}
                      onVariableSelect={(syntax) => {
                        const currentValue = button.title || "";
                        const newValue = currentValue + syntax;
                        handleUpdateButtonField(index, "title", newValue);
                      }}
                      placeholder="Insert Variable"
                      size="small"
                    />
                  </div>
                </div>
                <Button danger onClick={() => handleDeleteButton(index)}>
                  Remove Button
                </Button>
              </Space>
            </List.Item>
          )}
        />

        <Button
          type="dashed"
          onClick={handleAddButton}
          block
          disabled={buttons.length >= 3}
        >
          Add Button
        </Button>
      </Form.Item>
    </Card>
  );

  const renderProductMessageForm = () => (
    <Card
      title="Product Message Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      {interactiveType === "product" && (
        <>
          <Form.Item label="Message Body (Optional)">
            <Form.Item name="bodyText" noStyle>
              <TextArea
                rows={3}
                placeholder="Description text"
                maxLength={1024}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "bodyText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>

          <Form.Item
            label="Catalog"
            name="catalog_id"
            rules={[{ required: true, message: "Please select a catalog" }]}
          >
            <Select
              placeholder="Select a catalog"
              onChange={handleCatalogChange}
              loading={loadingCatalogs}
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) =>
                option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
              }
            >
              {catalogs.map((catalog) => (
                <Select.Option key={catalog.id} value={catalog.id}>
                  {catalog.name} ({catalog.product_count} products)
                </Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item
            label="Product"
            name="product_retailer_id"
            rules={[{ required: true, message: "Please select a product" }]}
          >
            <Select
              placeholder={
                selectedCatalog
                  ? "Select a product"
                  : "Please select a catalog first"
              }
              disabled={!selectedCatalog}
              loading={loadingProducts}
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) =>
                option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
              }
            >
              {products.map((product) => (
                <Select.Option
                  key={product.retailer_id}
                  value={product.retailer_id}
                >
                  {product.name} - {product.price}
                </Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Form.Item label="Footer Text (Optional)">
            <Form.Item name="footerText" noStyle>
              <TextArea
                rows={2}
                placeholder="e.g., 'Limited time offer'"
                maxLength={60}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "footerText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>
        </>
      )}

      {interactiveType === "product_list" && (
        <>
          <Form.Item
            label="Header Text"
            required
            rules={[
              {
                required: true,
                message: "Header text is required for product lists",
              },
              {
                max: 60,
                message: "Header must be 60 characters or less",
              },
            ]}
          >
            <Form.Item
              name="headerText"
              noStyle
              rules={[
                {
                  required: true,
                  message: "Header text is required for product lists",
                },
              ]}
            >
              <Input
                placeholder="e.g., 'Our Products'"
                maxLength={60}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "headerText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>

          <Form.Item
            label="Body Text"
            rules={[
              { required: true, message: "Body text is required" },
              { max: 1024, message: "Body must be 1024 characters or less" },
            ]}
          >
            <Form.Item
              name="bodyText"
              noStyle
              rules={[{ required: true, message: "Body text is required" }]}
            >
              <TextArea
                placeholder="Main message text"
                maxLength={1024}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "bodyText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>

          <Form.Item
            label="Catalog"
            name="catalog_id"
            rules={[{ required: true, message: "Please select a catalog" }]}
          >
            <Select
              placeholder="Select a catalog"
              onChange={handleCatalogChange}
              loading={loadingCatalogs}
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) =>
                option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
              }
            >
              {catalogs.map((catalog) => (
                <Select.Option key={catalog.id} value={catalog.id}>
                  {catalog.name} ({catalog.product_count} products)
                </Select.Option>
              ))}
            </Select>
          </Form.Item>

          <Divider orientation="left">Product Sections</Divider>

          {sections.map((section, sectionIdx) => (
            <div
              key={sectionIdx}
              style={{
                marginBottom: 16,
                border: "1px solid #f0f0f0",
                padding: 16,
              }}
            >
              <Form.Item
                label={`Section ${sectionIdx + 1} Title`}
                required
                validateStatus={
                  sectionValidationAttempted && !section.title?.trim() ? "error" : ""
                }
                help={
                  sectionValidationAttempted && !section.title?.trim()
                    ? "Section title is required"
                    : null
                }
              >
                <div>
                  <Input
                    value={section.title}
                    onChange={(e) =>
                      handleUpdateSectionTitle(sectionIdx, e.target.value)
                    }
                    placeholder="e.g., 'Summer Collection'"
                    maxLength={24}
                  />
                  <div style={{ marginTop: 8 }}>
                    <VariableHelper
                      variables={variables}
                      onVariableSelect={(syntax) => {
                        const currentValue = section.title || "";
                        const newValue = currentValue + syntax;
                        handleUpdateSectionTitle(sectionIdx, newValue);
                      }}
                      placeholder="Insert Variable"
                      size="small"
                    />
                  </div>
                </div>
              </Form.Item>

              <List
                header={<div>Products (1-30 per section)</div>}
                dataSource={section.product_items || []}
                renderItem={(item, itemIdx) => (
                  <List.Item>
                    <Space direction="vertical" style={{ width: "100%" }}>
                      <Select
                        placeholder="Select a product"
                        value={item.product_retailer_id}
                        onChange={(value) =>
                          handleProductChange(value, sectionIdx, itemIdx)
                        }
                        style={{ width: "100%" }}
                        loading={loadingProducts}
                        showSearch
                        optionFilterProp="children"
                        filterOption={(input, option) =>
                          option.children
                            .toLowerCase()
                            .indexOf(input.toLowerCase()) >= 0
                        }
                        disabled={!selectedCatalog}
                      >
                        {products.map((product) => (
                          <Select.Option
                            key={product.retailer_id}
                            value={product.retailer_id}
                          >
                            {product.name} - {product.price}
                          </Select.Option>
                        ))}
                      </Select>
                      <Button
                        danger
                        onClick={() => handleRemoveProduct(sectionIdx, itemIdx)}
                      >
                        Remove Product
                      </Button>
                    </Space>
                  </List.Item>
                )}
              />

              <Button
                type="dashed"
                onClick={() => handleAddProductToSection(sectionIdx)}
                block
                disabled={
                  (section.product_items || []).length >= 30 || !selectedCatalog
                }
              >
                Add Product
              </Button>
            </div>
          ))}

          <Button
            type="dashed"
            onClick={handleAddSection}
            block
            disabled={sections.length >= 10}
          >
            Add Section
          </Button>

          <Form.Item label="Footer Text (Optional)">
            <Form.Item name="footerText" noStyle>
              <TextArea
                rows={2}
                placeholder="e.g., 'Limited time offer'"
                maxLength={60}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "footerText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>
        </>
      )}

      {interactiveType === "catalog_message" && (
        <>
          <Form.Item
            label="Message Body"
            rules={[{ required: true, message: "Body text is required" }]}
          >
            <Form.Item
              name="bodyText"
              noStyle
              rules={[{ required: true, message: "Body text is required" }]}
            >
              <TextArea
                rows={4}
                placeholder="e.g., 'Check out our products'"
                maxLength={1024}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "bodyText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>

          <Form.Item label="Footer Text (Optional)">
            <Form.Item name="footerText" noStyle>
              <TextArea
                rows={2}
                placeholder="e.g., 'Limited time offer'"
                maxLength={60}
                showCount
              />
            </Form.Item>
            <div style={{ marginTop: 8 }}>
              <VariableHelper
                variables={variables}
                onVariableSelect={(syntax) =>
                  handleVariableSelect(syntax, "footerText")
                }
                placeholder="Insert Variable"
                size="small"
              />
            </div>
          </Form.Item>

          <Form.Item
            label="Select Catalog"
            name="catalog_id"
            rules={[{ required: true, message: "Please select a catalog" }]}
          >
            <Select
              placeholder="Select a catalog"
              loading={loadingCatalogs}
              notFoundContent={
                loadingCatalogs ? <Spin size="small" /> : "No catalogs found"
              }
            >
              {catalogs.map((catalog) => (
                <Select.Option key={catalog.id} value={catalog.id}>
                  {catalog.name} ({catalog.id})
                </Select.Option>
              ))}
            </Select>
          </Form.Item>
        </>
      )}
    </Card>
  );

  const renderCtaUrlForm = () => (
    <Card
      title="CTA URL Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        label="Message Body"
        rules={[
          { required: true, message: "Body text is required" },
          { max: 1024, message: "Maximum 1024 characters allowed" },
        ]}
      >
        <Form.Item
          name="bodyText"
          noStyle
          rules={[{ required: true, message: "Body text is required" }]}
        >
          <TextArea
            rows={4}
            placeholder="The message content that will appear above the button"
            maxLength={1024}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "bodyText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item
        label="Button Text"
        rules={[
          { required: true, message: "Button text is required" },
          { max: 20, message: "Maximum 20 characters allowed" },
        ]}
      >
        <Form.Item
          name="buttonText"
          noStyle
          rules={[{ required: true, message: "Button text is required" }]}
        >
          <Input
            placeholder="e.g., 'Shop Now' or 'Learn More'"
            maxLength={20}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "buttonText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item
        label="URL"
        rules={[
          { required: true, message: "URL is required" },
          {
            type: "url",
            message: "Please enter a valid URL (include https://)",
          },
        ]}
      >
        <Form.Item
          name="url"
          noStyle
          rules={[{ required: true, message: "URL is required" }]}
        >
          <Input placeholder="https://example.com" />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) => handleVariableSelect(syntax, "url")}
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>
    </Card>
  );

  const renderFlowMessageForm = () => (
    <Card
      title="WhatsApp Flow Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        label="Message Body"
        rules={[{ required: true, message: "Message body is required" }]}
      >
        <Form.Item
          name="bodyText"
          noStyle
          rules={[{ required: true, message: "Message body is required" }]}
        >
          <TextArea
            rows={4}
            placeholder="Enter your message text"
            maxLength={1024}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "bodyText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item
        label="Flow"
        name="flow_id"
        rules={[{ required: true, message: "Please select a flow" }]}
      >
        <Select
          placeholder="Select a flow"
          loading={loadingFlows}
          showSearch
          optionFilterProp="children"
          filterOption={(input, option) =>
            option.children.toLowerCase().includes(input.toLowerCase())
          }
          onChange={handleFlowChange}
          disabled={loadingFlows}
        >
          {flows.map((flow) => (
            <Select.Option key={flow.flowId} value={flow.flowId}>
              {flow.flowName} ({flow.categories?.join(", ") || "No categories"})
            </Select.Option>
          ))}
        </Select>
      </Form.Item>

      <Form.Item
        label="Flow Token"
        name="flow_token"
        rules={[{ required: true, message: "Flow token is required" }]}
        extra="Automatically generated as flowId_uuid"
      >
        <Input
          placeholder="Will be generated automatically"
          readOnly
          addonAfter={
            <Button
              size="small"
              onClick={() => {
                const flowId = form.getFieldValue("flow_id");
                if (flowId) {
                  form.setFieldsValue({
                    flow_token: generateFlowToken(flowId),
                  });
                } else {
                  message.warning("Please select a flow first");
                }
              }}
            >
              Regenerate
            </Button>
          }
        />
      </Form.Item>

      <Form.Item
        label="Button Text"
        name="flow_cta"
        rules={[
          { required: true, message: "Button text is required" },
          { max: 20, message: "Max 20 characters allowed" },
        ]}
      >
        <Input placeholder="e.g., 'Start Flow'" maxLength={20} showCount />
      </Form.Item>

      <Form.Item
        label="Flow Action Payload"
        name="flow_action_payload"
        extra="Automatically populated with first screen ID when you select a flow"
      >
        <TextArea rows={4} readOnly disabled={fetchingFlowJson} />
      </Form.Item>
    </Card>
  );

  const renderLocationRequestForm = () => (
    <Card
      title="Location Request Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        label="Message Body"
        rules={[{ required: true, message: "Body text is required" }]}
      >
        <Form.Item
          name="bodyText"
          noStyle
          rules={[{ required: true, message: "Body text is required" }]}
        >
          <TextArea
            rows={4}
            placeholder="e.g., 'Please share your location'"
            maxLength={1024}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "bodyText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>
    </Card>
  );

  const renderAddressMessageForm = () => (
    <Card
      title="Address Message Configuration"
      size="small"
      style={{ marginBottom: 16 }}
    >
      <Form.Item
        label="Message Body"
        rules={[{ required: true, message: "Body text is required" }]}
      >
        <Form.Item
          name="bodyText"
          noStyle
          rules={[{ required: true, message: "Body text is required" }]}
        >
          <TextArea
            rows={4}
            placeholder="e.g., 'Please share your delivery address'"
            maxLength={1024}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "bodyText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>

      <Form.Item label="Footer Text (Optional)">
        <Form.Item name="footerText" noStyle>
          <TextArea
            rows={2}
            placeholder="e.g., 'We need this for delivery'"
            maxLength={60}
            showCount
          />
        </Form.Item>
        <div style={{ marginTop: 8 }}>
          <VariableHelper
            variables={variables}
            onVariableSelect={(syntax) =>
              handleVariableSelect(syntax, "footerText")
            }
            placeholder="Insert Variable"
            size="small"
          />
        </div>
      </Form.Item>
    </Card>
  );

  // const renderFormByType = () => {
  //   switch (interactiveType) {
  //     case "list":
  //       return renderListMessageForm();
  //     case "button":
  //       return renderButtonsMessageForm();
  //     case "product":
  //     case "product_list":
  //     case "catalog_message":
  //       return renderProductMessageForm();
  //     case "cta_url":
  //       return renderCtaUrlForm();
  //     case "flow":
  //       return renderFlowMessageForm();
  //     case "location_request_message":
  //       return renderLocationRequestForm();
  //     case "address_message":
  //       return renderAddressMessageForm();
  //     default:
  //       return renderListMessageForm();
  //   }
  // };

  // Fix the renderFormByType function
  const renderFormByType = () => {
    console.log("renderFormByType - interactiveType:", interactiveType);

    switch (interactiveType) {
      case "list":
        return renderListMessageForm();
      case "button":
        return renderButtonsMessageForm();
      case "product":
      case "product_list":
      case "catalog_message":
        return renderProductMessageForm();
      case "cta_url":
        return renderCtaUrlForm();
      case "flow":
        return renderFlowMessageForm();
      case "location_request_message":
        return renderLocationRequestForm();
      case "address_message":
        return renderAddressMessageForm();
      default:
        return renderListMessageForm();
    }
  };

  const initializationRef = useRef(false);

  // Initialize form with node data if editing
  useEffect(() => {
    if (!visible) {
      initializationRef.current = false;
      return;
    }

    if (visible && nodeData && !initializationRef.current) {
      console.log(
        "Editing existing interactive message node with data:",
        nodeData
      );

      initializationRef.current = true;

      // Reset form first
      form.resetFields();

      // Batch all state updates
      const nodeInteractiveType = nodeData.interactiveType || "list";
      const nodeHeaderType = nodeData.headerType || "none";
      const nodeUploadedMedia = nodeData.uploadedMedia || null;

      // Set all state synchronously
      setInteractiveType(nodeInteractiveType);
      setHeaderType(nodeHeaderType);
      setUploadedMedia(nodeUploadedMedia);
      setSections(nodeData.sections || [{ rows: [] }]);
      setButtons(nodeData.buttons || []);
      setProducts(nodeData.products || []);
      setSelectedCatalog(nodeData.catalog_id || null);
      setSelectedFlowData(nodeData.selectedFlowData || null);

      if (nodeData.timeoutConfig) {
        setTimeoutConfig(nodeData.timeoutConfig);
      }

      // Prepare and set form values
      const formValues = {
        interactiveType: nodeInteractiveType,
        headerText: nodeData.headerText || "",
        bodyText: nodeData.bodyText || "",
        footerText: nodeData.footerText || "",
        buttonText: nodeData.buttonText || "",
        url: nodeData.url || "",
        flow_id: nodeData.flow_id || "",
        flow_token: nodeData.flow_token || "",
        flow_cta: nodeData.flow_cta || "",
        flow_action_payload: nodeData.flow_action_payload || "",
        productId: nodeData.productId || "",
        catalog_id: nodeData.catalog_id || "",
        unreadTimeout: nodeData.timeoutConfig?.unreadTimeout || 300,
        undeliveredTimeout: nodeData.timeoutConfig?.undeliveredTimeout || 60,
        headerType: nodeHeaderType,
        documentLink: nodeData.documentLink || "",
        documentFilename: nodeData.documentFilename || "",
      };

      console.log("Setting form values:", formValues);

      // Use requestAnimationFrame to ensure DOM is ready
      requestAnimationFrame(() => {
        form.setFieldsValue(formValues);
        console.log("Form values after setting:", form.getFieldsValue());
      });
    } else if (visible && !initializationRef.current) {
      console.log("Creating new interactive message node - resetting form");
      initializationRef.current = true;

      // Reset for new node
      form.resetFields();
      setInteractiveType("list");
      setSections([{ rows: [], product_items: [] }]);
      setButtons([]);
      setProducts([]);
      setSelectedCatalog(null);
      setShowAddProductModal(false);
      setEditingProduct(null);
      setSelectedFlowData(null);
      setHeaderType("none");
      setUploadedMedia(null);
      setTimeoutConfig({
        unreadTimeout: 300,
        undeliveredTimeout: 60,
      });

      requestAnimationFrame(() => {
        form.setFieldsValue({
          interactiveType: "list",
          headerText: "",
          bodyText: "",
          footerText: "",
          buttonText: "",
          url: "",
          flow_id: "",
          flow_token: "",
          flow_cta: "",
          flow_action_payload: "",
          productId: "",
          catalog_id: "",
          unreadTimeout: 300,
          undeliveredTimeout: 60,
          headerType: "none",
          name: "", // Initialize name field for new node
        });
      });
    }
  }, [visible, nodeData, form]);
  // Remove the separate debug useEffect as it's causing confusion
  // Add a debug effect to monitor form state
  useEffect(() => {
    if (visible) {
      console.log("=== FORM DEBUG ===");
      console.log("Interactive Type:", interactiveType);
      console.log("Form Values:", form.getFieldsValue());
      console.log("Node Data:", nodeData);
      console.log("Header Type:", headerType);
      console.log("Uploaded Media:", uploadedMedia);
      console.log("=== END DEBUG ===");
    }
  }, [visible, interactiveType, form, nodeData, headerType, uploadedMedia]);

  return (
    <>
      <BuilderDrawer
        title={
          <div className="chatbot-builder-drawer__title">
            <span className="chatbot-builder-drawer__title-icon">
              {getInteractiveTypeIcon(interactiveType)}
            </span>
            <span className="chatbot-builder-drawer__title-text">
              Interactive Message Configuration
            </span>
            <Tag color="green">{getInteractiveTypeLabel(interactiveType)}</Tag>
          </div>
        }
        width={1200}
        open={visible}
        onClose={onClose}
        footer={
          <Space style={{ width: "100%", justifyContent: "flex-end" }}>
            <Button onClick={onClose}>Cancel</Button>
            <Button
              type="primary"
              icon={<SendOutlined />}
              loading={loading}
              onClick={handleSave}
            >
              Save Configuration
            </Button>
          </Space>
        }
      >
        <Row gutter={24} style={{ height: '100%' }}>
          <Col span={16} style={{ height: '100%', overflowY: 'auto', paddingRight: 10 }}>
            <Form form={form} layout="vertical">
              {isNodeFlow && interactiveMessages.length > 0 && (
                <Card
                  size="small"
                  style={{
                    marginBottom: 16,
                    border: "1px solid #d1d5db",
                    backgroundColor: "#f0f7ff",
                  }}
                  title={
                    <Space>
                      <MessageOutlined />
                      <span>Quick Select Existing Message</span>
                    </Space>
                  }
                >
                  <Form.Item name="selectedMessageId">
                    <Select
                      placeholder="Choose an existing message to populate this node"
                      allowClear
                      onChange={handleMessageSelect}
                      showSearch
                      optionFilterProp="children"
                    >
                      {interactiveMessages.map((msg) => (
                        <Option key={msg.id} value={msg.id}>
                          {msg.name || "Untitled Message"}
                        </Option>
                      ))}
                    </Select>
                  </Form.Item>
                  <Text type="secondary" style={{ fontSize: "11px" }}>
                    Selecting a message will replace current configuration with the saved message's data.
                  </Text>
                </Card>
              )}
              {!isNodeFlow && (
                <Card
                  size="small"
                  style={{
                    marginBottom: 16,
                    border: "1px solid #d1d5db",
                    backgroundColor: "#f9fafb",
                  }}
                >
                  <Form.Item
                    label={
                      <span style={{ fontWeight: 600 }}>Message Name</span>
                    }
                    name="name"
                    rules={[
                      { required: true, message: "Please enter a message name" },
                    ]}
                  >
                    <Input placeholder="e.g., Welcome Offer, Support Menu..." />
                  </Form.Item>
                </Card>
              )}
              {/* Interactive Message Type Selection */}
              <Card
                title="Interactive Message Type"
                size="small"
                style={{ marginBottom: 16 }}
              >
                <Form.Item name="interactiveType">
                  <Select
                    placeholder="Select interactive message type"
                    onChange={(value) => setInteractiveType(value)}
                    value={interactiveType}
                  >
                    {/* ... [existing Select Options] ... */}
                    <Select.Option value="list">
                      <Space>
                        <AppstoreOutlined />
                        List Message
                      </Space>
                    </Select.Option>
                    <Select.Option value="button">
                      <Space>
                        <MessageOutlined />
                        Buttons Message
                      </Space>
                    </Select.Option>
                    <Select.Option value="product">
                      <Space>
                        <ShoppingOutlined />
                        Single Product
                      </Space>
                    </Select.Option>
                    <Select.Option value="product_list">
                      <Space>
                        <ShoppingOutlined />
                        Multi-Product
                      </Space>
                    </Select.Option>
                    <Select.Option value="catalog_message">
                      <Space>
                        <ShoppingOutlined />
                        Product Catalog
                      </Space>
                    </Select.Option>
                    <Select.Option value="cta_url">
                      <Space>
                        <LinkOutlined />
                        CTA Button
                      </Space>
                    </Select.Option>
                    <Select.Option value="flow">
                      <Space>
                        <MessageOutlined />
                        WhatsApp Flow
                      </Space>
                    </Select.Option>
                    <Select.Option value="location_request_message">
                      <Space>
                        <EnvironmentOutlined />
                        Location Request
                      </Space>
                    </Select.Option>
                    <Select.Option value="address_message">
                      <Space>
                        <EnvironmentOutlined />
                        Address Message
                      </Space>
                    </Select.Option>
                  </Select>
                </Form.Item>
              </Card>

              {/* Dynamic Form Based on Type */}
              {renderFormByType()}

              {/* Interactive Message Guidelines - Kept at bottom of form */}
              <Card title="Interactive Message Guidelines" size="small">
                <div style={{ fontSize: "12px", color: "#666" }}>
                  <Paragraph style={{ marginBottom: 8 }}>
                    <Text strong>WhatsApp Interactive Message Requirements:</Text>
                  </Paragraph>
                  <ul style={{ margin: 0, paddingLeft: 16 }}>
                    <li>Interactive messages can only be sent to users who have opted in</li>
                    <li>List messages can have up to 10 sections with 1 item each</li>
                    <li>Button messages can have up to 3 buttons</li>
                    <li>Product messages require a valid product catalog</li>
                    <li>CTA URLs must be valid and accessible</li>
                    <li>Location requests require user permission</li>
                  </ul>
                  <Divider style={{ margin: "12px 0" }} />
                  <Paragraph style={{ marginBottom: 8 }}>
                    <Text strong>Best Practices:</Text>
                  </Paragraph>
                  <ul style={{ margin: 0, paddingLeft: 16 }}>
                    <li>Keep interactive elements clear and actionable</li>
                    <li>Use descriptive button text and section titles</li>
                    <li>Test interactive messages before deployment</li>
                    <li>Provide fallback options for better user experience</li>
                  </ul>
                </div>
              </Card>
            </Form>
          </Col>

          <Col span={8} style={{ height: "100%", borderLeft: "1px solid #f0f0f0", paddingLeft: 20 }}>
            <div style={{ position: "sticky", top: 0 }}>
              <InteractiveMessagePreview
                headerText={formValues?.headerText}
                bodyText={formValues?.bodyText}
                footerText={formValues?.footerText}
                buttonText={formValues?.buttonText}
                url={formValues?.url}
                flow_cta={formValues?.flow_cta}
                interactiveType={interactiveType}
                headerType={headerType}
                uploadedMedia={uploadedMedia}
                sections={sections}
                buttons={buttons}
              />
            </div>
          </Col>
        </Row>

        {/* Add Product Modal */}
        <Modal
          title={editingProduct !== null ? "Edit Product" : "Add Product"}
          open={showAddProductModal}
          onOk={() => {
            const values = form.getFieldsValue([
              "productId",
              "productName",
              "productDescription",
              "productPrice",
            ]);
            if (values.productId && values.productName) {
              handleAddProduct({
                id: values.productId,
                name: values.productName,
                description: values.productDescription,
                price: values.productPrice,
              });
            } else {
              message.error("Please enter product ID and name");
            }
          }}
          onCancel={() => {
            setShowAddProductModal(false);
            setEditingProduct(null);
            form.resetFields([
              "productId",
              "productName",
              "productDescription",
              "productPrice",
            ]);
          }}
        >
          <Form.Item name="productId" label="Product ID" required>
            <Input placeholder="Enter product ID" />
          </Form.Item>
          <Form.Item name="productName" label="Product Name" required>
            <Input placeholder="Enter product name" />
          </Form.Item>
          <Form.Item name="productDescription" label="Product Description">
            <TextArea placeholder="Enter product description" rows={2} />
          </Form.Item>
          <Form.Item name="productPrice" label="Product Price">
            <Input placeholder="Enter product price" />
          </Form.Item>
        </Modal>

        {/* Media Upload Confirmation Modal */}
        <Modal
          title={`Upload ${headerType}`}
          open={mediaModalOpen}
          onOk={confirmMediaUpload}
          onCancel={cancelMediaUpload}
          confirmLoading={fileUploadLoader}
          okText="Upload"
          cancelText="Cancel"
        >
          <Spin spinning={fileUploadLoader}>
            <p>Are you sure you want to upload this {headerType}?</p>
            {selectedFile && (
              <div style={{ marginTop: 16 }}>
                <strong>File:</strong> {selectedFile.name}
                <br />
                <strong>Size:</strong>{" "}
                {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                <br />
                <strong>Type:</strong> {selectedFile.type}
              </div>
            )}
          </Spin>
        </Modal>
      </BuilderDrawer>
    </>
  );
};

export default ReactFlowInteractiveMessageModule;
